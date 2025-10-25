<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'ratings_card_register_rest_routes');

function ratings_card_register_rest_routes() {
    register_rest_route('ratings-card/v1', '/ratings', array(
        'methods' => 'GET',
        'callback' => 'ratings_card_rest_get_ratings',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => 'ratings_card_validate_id'
            ),
            'type' => array(
                'required' => false,
                'type' => 'string',
                'default' => 'imdb',
                'sanitize_callback' => 'sanitize_text_field',
                'enum' => array('imdb', 'tmdb')
            ),
            'mediaType' => array(
                'required' => false,
                'type' => 'string',
                'default' => 'movie',
                'sanitize_callback' => 'sanitize_text_field',
                'enum' => array('movie', 'show')
            )
        )
    ));
}

function ratings_card_validate_id($param, $request, $key) {
    $type = $request->get_param('type');
    
    if ($type === 'tmdb') {
        return preg_match('/^\d+$/', $param);
    } else {
        return preg_match('/^tt\d+$/', $param);
    }
}

function ratings_card_rest_get_ratings($request) {
    $ip = ratings_card_get_client_ip();
    
    if (!ratings_card_check_rate_limit($ip)) {
        return new WP_Error(
            'rate_limit_exceeded',
            'Too many requests. Please try again later.',
            array('status' => 429)
        );
    }
    
    $id = $request->get_param('id');
    $type = $request->get_param('type');
    $media_type = $request->get_param('mediaType');
    
    if ($type === 'tmdb') {
        $data = ratings_card_get_by_tmdb($id, $media_type, false);
    } else {
        $data = ratings_card_get_by_imdb($id, false);
    }
    
    if (isset($data['error']) && $data['error']) {
        return new WP_Error(
            'api_error',
            $data['message'],
            array('status' => 500)
        );
    }
    
    $response_data = array(
        'title' => isset($data['title']) ? $data['title'] : '',
        'poster_path' => isset($data['poster_path']) ? $data['poster_path'] : '',
        'release_date' => isset($data['release_date']) ? $data['release_date'] : '',
        'average' => isset($data['average']) ? $data['average'] : 0,
        'ratings' => isset($data['ratings']) ? $data['ratings'] : array(),
        'popularity' => isset($data['popularity']) ? $data['popularity'] : 0,
        'origin_country' => isset($data['origin_country']) ? $data['origin_country'] : array(),
        'lastUpdated' => isset($data['lastUpdated']) ? $data['lastUpdated'] : '',
        'lastFetchAt' => isset($data['lastFetchAt']) ? $data['lastFetchAt'] : ''
    );
    
    return rest_ensure_response($response_data);
}

function ratings_card_get_client_ip() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return sanitize_text_field($ip);
}

function ratings_card_check_rate_limit($ip) {
    $ip_safe = preg_replace('/[^a-zA-Z0-9]/', '_', $ip);
    $transient_key = 'ratings_card_rl_' . $ip_safe;
    
    $requests = get_transient($transient_key);
    
    if ($requests === false) {
        set_transient($transient_key, 1, 60);
        return true;
    }
    
    if ($requests >= 30) {
        return false;
    }
    
    set_transient($transient_key, $requests + 1, 60);
    return true;
}
