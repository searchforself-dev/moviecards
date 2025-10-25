<?php

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('ratings_card', 'ratings_card_shortcode_handler');

function ratings_card_shortcode_handler($atts) {
    $atts = shortcode_atts(array(
        'id' => '',
        'type' => 'imdb',
        'mediaType' => 'movie',
        'force_refresh' => false
    ), $atts, 'ratings_card');
    
    $id = sanitize_text_field($atts['id']);
    $type = sanitize_text_field($atts['type']);
    $media_type = sanitize_text_field($atts['mediaType']);
    $force_refresh = filter_var($atts['force_refresh'], FILTER_VALIDATE_BOOLEAN);
    
    if (empty($id)) {
        return '<div class="ratings-card-error">Error: No ID provided. Use: [ratings_card id="tt0111161" type="imdb"]</div>';
    }
    
    if ($force_refresh && !current_user_can('manage_options')) {
        $force_refresh = false;
    }
    
    if ($type === 'tmdb') {
        $data = ratings_card_get_by_tmdb($id, $media_type, $force_refresh);
    } else {
        $data = ratings_card_get_by_imdb($id, $force_refresh);
    }
    
    if (isset($data['error']) && $data['error']) {
        return '<div class="ratings-card-error">' . esc_html($data['message']) . '</div>';
    }
    
    return ratings_card_render_html($data, $id, $type);
}

function ratings_card_render_html($data, $id, $type) {
    ob_start();
    
    $title = isset($data['title']) ? $data['title'] : 'Unknown Title';
    $poster = isset($data['poster_path']) ? $data['poster_path'] : '';
    $release_date = isset($data['release_date']) ? $data['release_date'] : '';
    $year = $release_date ? date('Y', strtotime($release_date)) : '';
    $tagline = isset($data['tagline']) ? $data['tagline'] : '';
    $overview = isset($data['overview']) ? $data['overview'] : '';
    $runtime = isset($data['runtime']) ? $data['runtime'] : 0;
    $popularity = isset($data['popularity']) ? $data['popularity'] : 0;
    $origin_country = isset($data['origin_country']) && is_array($data['origin_country']) ? implode(', ', $data['origin_country']) : '';
    $homepage = isset($data['homepage']) ? $data['homepage'] : '';
    $imdb_id = isset($data['imdb_id']) ? $data['imdb_id'] : $id;
    $ratings = isset($data['ratings']) ? $data['ratings'] : array();
    $average = isset($data['average']) ? $data['average'] : 0;
    $last_updated = isset($data['lastUpdated']) ? $data['lastUpdated'] : '';
    $last_fetch = isset($data['lastFetchAt']) ? $data['lastFetchAt'] : '';
    
    $overview_short = strlen($overview) > 160 ? substr($overview, 0, 157) . '...' : $overview;
    
    $total_reviews = 0;
    foreach ($ratings as $source => $rating_data) {
        if (isset($rating_data['votes'])) {
            $total_reviews += $rating_data['votes'];
        }
    }
    
    $min_reviews = get_option('ratings_card_min_reviews', 10000);
    $is_highly_reviewed = $total_reviews >= $min_reviews;
    
    $imdb_url = 'https://www.imdb.com/title/' . $imdb_id . '/';
    if ($homepage) {
        $poster_link = $homepage;
    } else {
        $poster_link = $imdb_url;
    }
    
    ?>
    <div class="ratings-card" data-id="<?php echo esc_attr($id); ?>" data-type="<?php echo esc_attr($type); ?>">
        
        <?php if ($poster): ?>
        <div class="ratings-card-poster">
            <a href="<?php echo esc_url($poster_link); ?>" target="_blank" rel="noopener noreferrer">
                <img src="<?php echo esc_url($poster); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
            </a>
        </div>
        <?php endif; ?>
        
        <div class="ratings-card-content">
            
            <div class="ratings-card-header">
                <h3 class="ratings-card-title">
                    <?php echo esc_html($title); ?>
                    <?php if ($year): ?>
                        <span class="ratings-card-year">(<?php echo esc_html($year); ?>)</span>
                    <?php endif; ?>
                </h3>
                <?php if ($tagline): ?>
                    <p class="ratings-card-tagline"><?php echo esc_html($tagline); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if ($overview_short): ?>
            <p class="ratings-card-overview"><?php echo esc_html($overview_short); ?></p>
            <?php endif; ?>
            
            <div class="ratings-card-score-main">
                <div class="ratings-card-average">
                    <span class="score-value"><?php echo esc_html(number_format($average, 1)); ?></span>
                    <span class="score-label">/ 10</span>
                </div>
                <div class="ratings-card-score-meta">
                    <span>Average of <?php echo count($ratings); ?> sources</span>
                    <?php if ($is_highly_reviewed): ?>
                        <span class="ratings-badge-highly-reviewed">Highly Reviewed</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($ratings)): ?>
            <div class="ratings-card-sources">
                
                <?php if (isset($ratings['imdb'])): ?>
                <div class="rating-source imdb">
                    <span class="source-name">IMDb</span>
                    <span class="source-score"><?php echo esc_html(number_format($ratings['imdb']['score'], 1)); ?></span>
                    <?php if (isset($ratings['imdb']['votes'])): ?>
                        <span class="source-votes">(<?php echo esc_html(number_format($ratings['imdb']['votes'])); ?>)</span>
                    <?php endif; ?>
                    <?php if (isset($ratings['imdb']['url'])): ?>
                        <a href="<?php echo esc_url($ratings['imdb']['url']); ?>" target="_blank" rel="noopener noreferrer" class="source-link">View</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($ratings['metacritic'])): ?>
                <div class="rating-source metacritic">
                    <span class="source-name">Metacritic</span>
                    <span class="source-score"><?php echo esc_html(number_format($ratings['metacritic']['score'], 0)); ?></span>
                    <?php if (isset($ratings['metacritic']['url'])): ?>
                        <a href="<?php echo esc_url($ratings['metacritic']['url']); ?>" target="_blank" rel="noopener noreferrer" class="source-link">View</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($ratings['rottenTomatoes'])): ?>
                <div class="rating-source rotten-tomatoes">
                    <span class="source-name">RT</span>
                    <?php if (isset($ratings['rottenTomatoes']['tomatometer'])): ?>
                        <span class="source-score">Tomatometer <?php echo esc_html(number_format($ratings['rottenTomatoes']['tomatometer'], 0)); ?>%</span>
                    <?php endif; ?>
                    <?php if (isset($ratings['rottenTomatoes']['audience'])): ?>
                        <span class="source-score"> / Audience <?php echo esc_html(number_format($ratings['rottenTomatoes']['audience'], 0)); ?>%</span>
                    <?php endif; ?>
                    <?php if (isset($ratings['rottenTomatoes']['url'])): ?>
                        <a href="<?php echo esc_url($ratings['rottenTomatoes']['url']); ?>" target="_blank" rel="noopener noreferrer" class="source-link">View</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($ratings['letterboxd'])): ?>
                <div class="rating-source letterboxd">
                    <span class="source-name">Letterboxd</span>
                    <span class="source-score"><?php echo esc_html(number_format($ratings['letterboxd']['score'], 1)); ?></span>
                    <?php if (isset($ratings['letterboxd']['url'])): ?>
                        <a href="<?php echo esc_url($ratings['letterboxd']['url']); ?>" target="_blank" rel="noopener noreferrer" class="source-link">View</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
            </div>
            <?php endif; ?>
            
            <div class="ratings-card-footer">
                <?php
                $footer_parts = array();
                if ($popularity > 0) {
                    $footer_parts[] = 'Popularity ' . number_format($popularity, 1);
                }
                if ($origin_country) {
                    $footer_parts[] = 'Country ' . esc_html($origin_country);
                }
                if ($runtime > 0) {
                    $footer_parts[] = 'Runtime ' . $runtime . ' min';
                }
                echo implode(' • ', $footer_parts);
                ?>
            </div>
            
            <?php if ($last_fetch): ?>
            <div class="ratings-card-freshness">
                Updated <?php echo esc_html(human_time_diff(strtotime($last_fetch), current_time('timestamp'))); ?> ago
            </div>
            <?php endif; ?>
            
        </div>
        
    </div>
    <?php
    
    return ob_get_clean();
}
