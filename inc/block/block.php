<?php

if (!defined('ABSPATH')) {
    exit;
}

function ratings_card_register_block() {
    if (!function_exists('register_block_type')) {
        return;
    }
    
    wp_register_script(
        'ratings-card-block-editor',
        RATINGS_CARD_PLUGIN_URL . 'inc/block/build/index.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
        RATINGS_CARD_VERSION,
        true
    );
    
    register_block_type('ratings-card/movie-ratings', array(
        'editor_script' => 'ratings-card-block-editor',
        'render_callback' => 'ratings_card_block_render',
        'attributes' => array(
            'id' => array(
                'type' => 'string',
                'default' => ''
            ),
            'type' => array(
                'type' => 'string',
                'default' => 'imdb'
            ),
            'mediaType' => array(
                'type' => 'string',
                'default' => 'movie'
            )
        )
    ));
}

function ratings_card_block_render($attributes) {
    $id = isset($attributes['id']) ? $attributes['id'] : '';
    $type = isset($attributes['type']) ? $attributes['type'] : 'imdb';
    $media_type = isset($attributes['mediaType']) ? $attributes['mediaType'] : 'movie';
    
    return ratings_card_shortcode_handler(array(
        'id' => $id,
        'type' => $type,
        'mediaType' => $media_type
    ));
}
