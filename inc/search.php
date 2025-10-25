<?php

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('ratings_search', 'ratings_card_search_shortcode_handler');
add_action('wp_ajax_ratings_card_search', 'ratings_card_ajax_search');
add_action('wp_ajax_nopriv_ratings_card_search', 'ratings_card_ajax_search');

function ratings_card_search_shortcode_handler($atts) {
    $atts = shortcode_atts(array(
        'placeholder' => 'Search for movies...',
        'results_limit' => 10
    ), $atts, 'ratings_search');
    
    $placeholder = sanitize_text_field($atts['placeholder']);
    $results_limit = absint($atts['results_limit']);
    
    ob_start();
    ?>
    <div class="ratings-card-search-container">
        <div class="ratings-card-search-form">
            <input 
                type="text" 
                class="ratings-card-search-input" 
                placeholder="<?php echo esc_attr($placeholder); ?>"
                autocomplete="off"
                data-results-limit="<?php echo esc_attr($results_limit); ?>"
            />
            <button type="button" class="ratings-card-search-btn">
                <span class="search-icon">🔍</span>
                <span class="search-text">Search</span>
            </button>
        </div>
        <div class="ratings-card-search-results"></div>
        <div class="ratings-card-selected-movie"></div>
    </div>
    <?php
    return ob_get_clean();
}

function ratings_card_search_movies($query, $page = 1) {
    $query = sanitize_text_field($query);

    if (empty($query)) {
        return array(
            'error' => true,
            'message' => 'Search query cannot be empty.'
        );
    }

    $cache_key = 'ratings_card_search_' . md5($query . '_' . $page);

    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $tmdb_api_key = get_option('ratings_card_tmdb_api_key', '');

    if (empty($tmdb_api_key)) {
        return array(
            'error' => true,
            'message' => 'TMDB API key not configured. Please set it in Settings → Ratings Card.'
        );
    }

    $url = 'https://api.themoviedb.org/3/search/movie?' . http_build_query(array(
        'api_key' => $tmdb_api_key,
        'query' => $query,
        'page' => $page,
        'include_adult' => 'false'
    ));

    $response = wp_remote_get($url, array(
        'timeout' => 15,
        'headers' => array(
            'Accept' => 'application/json'
        )
    ));

    if (is_wp_error($response)) {
        return array(
            'error' => true,
            'message' => 'Search request failed: ' . $response->get_error_message()
        );
    }

    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code !== 200) {
        return array(
            'error' => true,
            'message' => 'Search API returned error code: ' . $http_code
        );
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return array(
            'error' => true,
            'message' => 'Failed to parse search response: ' . json_last_error_msg()
        );
    }

    $sanitized_results = array(
        'page' => isset($data['page']) ? absint($data['page']) : 1,
        'total_results' => isset($data['total_results']) ? absint($data['total_results']) : 0,
        'total_pages' => isset($data['total_pages']) ? absint($data['total_pages']) : 0,
        'results' => array()
    );

    if (isset($data['results']) && is_array($data['results'])) {
        foreach ($data['results'] as $movie) {
            $sanitized_results['results'][] = array(
                'id' => isset($movie['id']) ? absint($movie['id']) : 0,
                'title' => isset($movie['title']) ? sanitize_text_field($movie['title']) : '',
                'original_title' => isset($movie['original_title']) ? sanitize_text_field($movie['original_title']) : '',
                'overview' => isset($movie['overview']) ? sanitize_textarea_field($movie['overview']) : '',
                'poster_path' => isset($movie['poster_path']) && $movie['poster_path'] ? 'https://image.tmdb.org/t/p/w500' . sanitize_text_field($movie['poster_path']) : '',
                'backdrop_path' => isset($movie['backdrop_path']) && $movie['backdrop_path'] ? 'https://image.tmdb.org/t/p/w1280' . sanitize_text_field($movie['backdrop_path']) : '',
                'release_date' => isset($movie['release_date']) ? sanitize_text_field($movie['release_date']) : '',
                'vote_average' => isset($movie['vote_average']) ? floatval($movie['vote_average']) : 0,
                'vote_count' => isset($movie['vote_count']) ? absint($movie['vote_count']) : 0,
                'popularity' => isset($movie['popularity']) ? floatval($movie['popularity']) : 0
            );
        }
    }

    set_transient($cache_key, $sanitized_results, 3600);

    return $sanitized_results;
}

function ratings_card_ajax_search() {
    check_ajax_referer('ratings_card_search_nonce', 'nonce');
    
    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    
    if (empty($query)) {
        wp_send_json_error(array(
            'message' => 'Search query is required.'
        ));
    }
    
    if (strlen($query) < 2) {
        wp_send_json_error(array(
            'message' => 'Please enter at least 2 characters.'
        ));
    }
    
    // Fetch search results from the TMDB API.
    $results = ratings_card_search_movies($query, $page);
    
    if (isset($results['error']) && $results['error']) {
        wp_send_json_error(array(
            'message' => $results['message']
        ));
    }
    
    $html = '';
    if (!empty($results['results'])) {
        $html .= '<div class="search-results-header">';
        $html .= '<p>Found ' . esc_html($results['total_results']) . ' results for "' . esc_html($query) . '"</p>';
        $html .= '</div>';
        
        $html .= '<div class="search-results-grid">';
        foreach ($results['results'] as $movie) {
            $html .= ratings_card_render_search_result_item($movie);
        }
        $html .= '</div>';
        
        if ($results['total_pages'] > 1) {
            $html .= '<div class="search-results-pagination">';
            $html .= '<p>Page ' . esc_html($results['page']) . ' of ' . esc_html($results['total_pages']) . '</p>';
            
            if ($results['page'] < $results['total_pages']) {
                $html .= '<button type="button" class="load-more-results" data-page="' . esc_attr($results['page'] + 1) . '" data-query="' . esc_attr($query) . '">Load More</button>';
            }
            $html .= '</div>';
        }
    } else {
        $html .= '<div class="search-no-results">';
        $html .= '<p>No movies found for "' . esc_html($query) . '". Try a different search term.</p>';
        $html .= '</div>';
    }
    
    wp_send_json_success(array(
        'html' => $html,
        'total_results' => $results['total_results'],
        'page' => $results['page'],
        'total_pages' => $results['total_pages']
    ));
}

function ratings_card_render_search_result_item($movie) {
    $year = '';
    if (!empty($movie['release_date'])) {
        $year = date('Y', strtotime($movie['release_date']));
    }
    
    $overview_short = strlen($movie['overview']) > 120 ? substr($movie['overview'], 0, 117) . '...' : $movie['overview'];
    
    $poster = $movie['poster_path'] ? $movie['poster_path'] : RATINGS_CARD_PLUGIN_URL . 'assets/images/no-poster.png';
    
    ob_start();
    ?>
    <div class="search-result-item" data-movie-id="<?php echo esc_attr($movie['id']); ?>">
        <div class="search-result-poster">
            <?php if ($movie['poster_path']): ?>
                <img src="<?php echo esc_url($movie['poster_path']); ?>" alt="<?php echo esc_attr($movie['title']); ?>" loading="lazy">
            <?php else: ?>
                <div class="no-poster-placeholder">
                    <span>No Poster</span>
                </div>
            <?php endif; ?>
        </div>
        <div class="search-result-info">
            <h4 class="search-result-title">
                <?php echo esc_html($movie['title']); ?>
                <?php if ($year): ?>
                    <span class="search-result-year">(<?php echo esc_html($year); ?>)</span>
                <?php endif; ?>
            </h4>
            <?php if ($movie['overview']): ?>
                <p class="search-result-overview"><?php echo esc_html($overview_short); ?></p>
            <?php endif; ?>
            <div class="search-result-meta">
                <?php if ($movie['vote_average'] > 0): ?>
                    <span class="tmdb-rating">★ <?php echo esc_html(number_format($movie['vote_average'], 1)); ?>/10</span>
                <?php endif; ?>
            </div>
            <button type="button" class="show-ratings-btn" data-movie-id="<?php echo esc_attr($movie['id']); ?>" data-movie-title="<?php echo esc_attr($movie['title']); ?>">
                View Full Ratings
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
