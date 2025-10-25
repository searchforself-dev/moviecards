<?php
/**
 * Plugin Name: Ratings Card
 * Plugin URI: https://github.com/yourusername/ratings-card
 * Description: Displays movie and TV show ratings from multiple sources (IMDb, Metacritic, Rotten Tomatoes, Letterboxd) using RapidAPI. Server-side caching with WordPress transients. No user login required.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ratings-card
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RATINGS_CARD_VERSION', '1.0.0');
define('RATINGS_CARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RATINGS_CARD_PLUGIN_URL', plugin_dir_url(__FILE__));

class RatingsCard {
    
    private static $instance = null;
    private $shortcode_present = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once RATINGS_CARD_PLUGIN_DIR . 'inc/api.php';
        require_once RATINGS_CARD_PLUGIN_DIR . 'inc/settings.php';
        require_once RATINGS_CARD_PLUGIN_DIR . 'inc/shortcodes.php';
        require_once RATINGS_CARD_PLUGIN_DIR . 'inc/search.php';
        require_once RATINGS_CARD_PLUGIN_DIR . 'inc/rest.php';
        require_once RATINGS_CARD_PLUGIN_DIR . 'inc/block/block.php';
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('init', array($this, 'register_block'));
    }
    
    public function activate() {
        if (!get_option('ratings_card_api_host')) {
            add_option('ratings_card_api_host', 'movies-ratings2.p.rapidapi.com');
        }
        if (!get_option('ratings_card_cache_ttl')) {
            add_option('ratings_card_cache_ttl', 360);
        }
        if (!get_option('ratings_card_min_reviews')) {
            add_option('ratings_card_min_reviews', 10000);
        }
    }
    
    public function deactivate() {
    }
    
    public function enqueue_frontend_assets() {
        global $post;
        
        $should_enqueue = false;
        $has_search = false;
        
        if (is_a($post, 'WP_Post')) {
            if (has_shortcode($post->post_content, 'ratings_card')) {
                $should_enqueue = true;
            }
            if (has_shortcode($post->post_content, 'ratings_search')) {
                $should_enqueue = true;
                $has_search = true;
            }
        }
        
        if (has_block('ratings-card/movie-ratings')) {
            $should_enqueue = true;
        }
        
        if ($should_enqueue) {
            wp_enqueue_style(
                'ratings-card-frontend',
                RATINGS_CARD_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                RATINGS_CARD_VERSION
            );
            
            wp_enqueue_script(
                'ratings-card-frontend',
                RATINGS_CARD_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                RATINGS_CARD_VERSION,
                true
            );
            
            wp_localize_script('ratings-card-frontend', 'ratingsCardData', array(
                'restUrl' => rest_url('ratings-card/v1/ratings'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'searchNonce' => wp_create_nonce('ratings_card_search_nonce'),
                'nonce' => wp_create_nonce('wp_rest'),
                'hasSearch' => $has_search
            ));
        }
    }
    
    public function register_block() {
        ratings_card_register_block();
    }
}

function ratings_card() {
    return RatingsCard::get_instance();
}

ratings_card();
