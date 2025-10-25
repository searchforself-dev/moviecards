<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'ratings_card_add_settings_page');
add_action('admin_init', 'ratings_card_register_settings');
add_action('admin_post_ratings_card_clear_cache', 'ratings_card_clear_cache_action');

function ratings_card_add_settings_page() {
    add_options_page(
        'Ratings Card Settings',
        'Ratings Card',
        'manage_options',
        'ratings-card-settings',
        'ratings_card_render_settings_page'
    );
}

function ratings_card_register_settings() {
    register_setting('ratings_card_settings', 'ratings_card_api_host', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'movies-ratings2.p.rapidapi.com'
    ));
    
    register_setting('ratings_card_settings', 'ratings_card_api_key', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ));
    
    register_setting('ratings_card_settings', 'ratings_card_tmdb_api_key', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ));
    
    register_setting('ratings_card_settings', 'ratings_card_cache_ttl', array(
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 360
    ));
    
    register_setting('ratings_card_settings', 'ratings_card_min_reviews', array(
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 10000
    ));
    
    add_settings_section(
        'ratings_card_api_section',
        'RapidAPI Configuration',
        'ratings_card_api_section_callback',
        'ratings-card-settings'
    );
    
    add_settings_field(
        'ratings_card_api_host',
        'RapidAPI Host',
        'ratings_card_api_host_field',
        'ratings-card-settings',
        'ratings_card_api_section'
    );
    
    add_settings_field(
        'ratings_card_api_key',
        'RapidAPI Key',
        'ratings_card_api_key_field',
        'ratings-card-settings',
        'ratings_card_api_section'
    );
    
    add_settings_field(
        'ratings_card_tmdb_api_key',
        'TMDB API Key',
        'ratings_card_tmdb_api_key_field',
        'ratings-card-settings',
        'ratings_card_api_section'
    );
    
    add_settings_section(
        'ratings_card_cache_section',
        'Cache Configuration',
        'ratings_card_cache_section_callback',
        'ratings-card-settings'
    );
    
    add_settings_field(
        'ratings_card_cache_ttl',
        'Cache TTL (minutes)',
        'ratings_card_cache_ttl_field',
        'ratings-card-settings',
        'ratings_card_cache_section'
    );
    
    add_settings_field(
        'ratings_card_min_reviews',
        'Minimum Reviews for "Highly Reviewed" Badge',
        'ratings_card_min_reviews_field',
        'ratings-card-settings',
        'ratings_card_cache_section'
    );
}

function ratings_card_api_section_callback() {
    echo '<p>Configure your RapidAPI credentials for the Movies Ratings API.</p>';
}

function ratings_card_cache_section_callback() {
    echo '<p>Configure caching behavior and display thresholds.</p>';
}

function ratings_card_api_host_field() {
    $value = get_option('ratings_card_api_host', 'movies-ratings2.p.rapidapi.com');
    echo '<input type="text" name="ratings_card_api_host" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">Default: movies-ratings2.p.rapidapi.com</p>';
}

function ratings_card_api_key_field() {
    $value = get_option('ratings_card_api_key', '');
    echo '<input type="password" name="ratings_card_api_key" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off" />';
    echo '<p class="description">Your RapidAPI key for Movies Ratings API. This is stored securely and never exposed to the browser.</p>';
}

function ratings_card_tmdb_api_key_field() {
    $value = get_option('ratings_card_tmdb_api_key', '');
    echo '<input type="password" name="ratings_card_tmdb_api_key" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off" />';
    echo '<p class="description">Your TMDB API key for movie search. Get it free at <a href="https://www.themoviedb.org/settings/api" target="_blank">themoviedb.org</a>. Never exposed to the browser.</p>';
}

function ratings_card_cache_ttl_field() {
    $value = get_option('ratings_card_cache_ttl', 360);
    echo '<input type="number" name="ratings_card_cache_ttl" value="' . esc_attr($value) . '" min="1" max="10080" />';
    echo '<p class="description">How long to cache ratings data (in minutes). Default: 360 (6 hours)</p>';
}

function ratings_card_min_reviews_field() {
    $value = get_option('ratings_card_min_reviews', 10000);
    echo '<input type="number" name="ratings_card_min_reviews" value="' . esc_attr($value) . '" min="0" />';
    echo '<p class="description">Minimum total reviews to show "Highly Reviewed" badge. Default: 10,000</p>';
}

function ratings_card_render_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    ?>
    <div class="wrap">
        <h1>Ratings Card Settings</h1>
        
        <?php settings_errors('ratings_card_settings'); ?>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('ratings_card_settings');
            do_settings_sections('ratings-card-settings');
            submit_button();
            ?>
        </form>
        
        <hr>
        
        <h2>Cache Management</h2>
        <p>Clear all cached ratings data. This will force fresh API calls on next request.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="ratings_card_clear_cache">
            <?php wp_nonce_field('ratings_card_clear_cache', 'ratings_card_nonce'); ?>
            <?php submit_button('Clear All Cache', 'secondary'); ?>
        </form>
        
        <hr>
        
        <h2>Usage Instructions</h2>
        <h3>Shortcode</h3>
        <p>Use the following shortcode in posts or pages:</p>
        <pre><code>[ratings_card id="tt0111161" type="imdb"]</code></pre>
        
        <h4>Shortcode Attributes:</h4>
        <ul>
            <li><strong>id</strong> (required): IMDb ID (e.g., tt0111161) or TMDB ID (numeric)</li>
            <li><strong>type</strong> (optional): "imdb" or "tmdb" (default: imdb)</li>
            <li><strong>mediaType</strong> (optional): "movie" or "show" (for TMDB IDs)</li>
        </ul>
        
        <h4>Examples:</h4>
        <pre><code>[ratings_card id="tt0111161" type="imdb"]
[ratings_card id="278" type="tmdb" mediaType="movie"]
[ratings_card id="1396" type="tmdb" mediaType="show"]</code></pre>
        
        <h3>Gutenberg Block</h3>
        <p>In the block editor, search for "Ratings Card" and configure the ID and type.</p>
        
        <h3>REST API</h3>
        <p>Public endpoint for AJAX requests:</p>
        <pre><code>GET <?php echo esc_html(rest_url('ratings-card/v1/ratings')); ?>?id=tt0111161&type=imdb</code></pre>
    </div>
    <?php
}

function ratings_card_clear_cache_action() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to perform this action.'));
    }
    
    check_admin_referer('ratings_card_clear_cache', 'ratings_card_nonce');
    
    global $wpdb;
    
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_ratings_card_%' 
        OR option_name LIKE '_transient_timeout_ratings_card_%'"
    );
    
    wp_cache_flush();
    
    add_settings_error(
        'ratings_card_settings',
        'cache_cleared',
        'All ratings cache has been cleared successfully.',
        'success'
    );
    
    set_transient('ratings_card_admin_notice', get_settings_errors('ratings_card_settings'), 30);
    
    wp_redirect(admin_url('options-general.php?page=ratings-card-settings'));
    exit;
}
