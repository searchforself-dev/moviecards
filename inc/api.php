<?php

if (!defined('ABSPATH')) {
    exit;
}

function ratings_card_get_by_imdb($imdb_id, $force_refresh = false) {
    $imdb_id = sanitize_text_field($imdb_id);
    
    if (!preg_match('/^tt\d+$/', $imdb_id)) {
        return array(
            'error' => true,
            'message' => 'Invalid IMDb ID format. Must start with "tt" followed by numbers.'
        );
    }
    
    $cache_key = 'ratings_card_imdb_' . $imdb_id;
    
    if (!$force_refresh) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
    }
    
    $api_host = get_option('ratings_card_api_host', 'movies-ratings2.p.rapidapi.com');
    $api_key = get_option('ratings_card_api_key', '');
    
    if (empty($api_key)) {
        return array(
            'error' => true,
            'message' => 'RapidAPI key not configured. Please set it in Settings → Ratings Card.'
        );
    }
    
    $url = 'https://' . $api_host . '/ratings?id=' . urlencode($imdb_id);
    
    $response = ratings_card_fetch_api($url, $api_host, $api_key);
    
    if (isset($response['error']) && $response['error']) {
        return $response;
    }
    
    $response['lastFetchAt'] = current_time('mysql');
    $response['cacheKey'] = $cache_key;
    
    $ttl_minutes = get_option('ratings_card_cache_ttl', 360);
    $ttl_seconds = $ttl_minutes * 60;
    
    set_transient($cache_key, $response, $ttl_seconds);
    
    return $response;
}

function ratings_card_get_by_tmdb($tmdb_id, $media_type = 'movie', $force_refresh = false) {
    $tmdb_id = sanitize_text_field($tmdb_id);
    $media_type = sanitize_text_field($media_type);
    
    if (!preg_match('/^\d+$/', $tmdb_id)) {
        return array(
            'error' => true,
            'message' => 'Invalid TMDB ID format. Must be numeric.'
        );
    }
    
    if (!in_array($media_type, array('movie', 'show'), true)) {
        $media_type = 'movie';
    }
    
    $cache_key = 'ratings_card_tmdb_' . $tmdb_id . '_' . $media_type;
    
    if (!$force_refresh) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
    }
    
    $api_host = get_option('ratings_card_api_host', 'movies-ratings2.p.rapidapi.com');
    $api_key = get_option('ratings_card_api_key', '');
    
    if (empty($api_key)) {
        return array(
            'error' => true,
            'message' => 'RapidAPI key not configured. Please set it in Settings → Ratings Card.'
        );
    }
    
    $url = 'https://' . $api_host . '/ratings?id=' . urlencode($tmdb_id) . '&mediaType=' . urlencode($media_type);
    
    $response = ratings_card_fetch_api($url, $api_host, $api_key);
    
    if (isset($response['error']) && $response['error']) {
        return $response;
    }
    
    $response['lastFetchAt'] = current_time('mysql');
    $response['cacheKey'] = $cache_key;
    
    $ttl_minutes = get_option('ratings_card_cache_ttl', 360);
    $ttl_seconds = $ttl_minutes * 60;
    
    set_transient($cache_key, $response, $ttl_seconds);
    
    return $response;
}

function ratings_card_fetch_api($url, $host, $api_key) {
    $ch = curl_init();
    
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'x-rapidapi-host: ' . $host,
            'x-rapidapi-key: ' . $api_key
        ),
    ));
    
    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    curl_close($ch);
    
    if ($curl_error) {
        return array(
            'error' => true,
            'message' => 'API request failed: ' . $curl_error
        );
    }
    
    if ($http_code !== 200) {
        return array(
            'error' => true,
            'message' => 'API returned error code: ' . $http_code,
            'http_code' => $http_code
        );
    }
    
    $data = json_decode($response_body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return array(
            'error' => true,
            'message' => 'Failed to parse API response: ' . json_last_error_msg()
        );
    }
    
    return ratings_card_sanitize_response($data);
}

function ratings_card_sanitize_response($data) {
    if (!is_array($data)) {
        return array('error' => true, 'message' => 'Invalid response format');
    }
    
    $sanitized = array();
    
    if (isset($data['title'])) {
        $sanitized['title'] = sanitize_text_field($data['title']);
    }
    
    if (isset($data['poster_path'])) {
        $sanitized['poster_path'] = esc_url_raw($data['poster_path']);
    }
    
    if (isset($data['release_date'])) {
        $sanitized['release_date'] = sanitize_text_field($data['release_date']);
    }
    
    if (isset($data['tagline'])) {
        $sanitized['tagline'] = sanitize_text_field($data['tagline']);
    }
    
    if (isset($data['overview'])) {
        $sanitized['overview'] = sanitize_textarea_field($data['overview']);
    }
    
    if (isset($data['runtime'])) {
        $sanitized['runtime'] = absint($data['runtime']);
    }
    
    if (isset($data['popularity'])) {
        $sanitized['popularity'] = floatval($data['popularity']);
    }
    
    if (isset($data['origin_country']) && is_array($data['origin_country'])) {
        $sanitized['origin_country'] = array_map('sanitize_text_field', $data['origin_country']);
    }
    
    if (isset($data['homepage'])) {
        $sanitized['homepage'] = esc_url_raw($data['homepage']);
    }
    
    if (isset($data['imdb_id'])) {
        $sanitized['imdb_id'] = sanitize_text_field($data['imdb_id']);
    }
    
    if (isset($data['ratings']) && is_array($data['ratings'])) {
        $sanitized['ratings'] = array();
        foreach ($data['ratings'] as $source => $rating_data) {
            $sanitized['ratings'][$source] = ratings_card_sanitize_rating($rating_data);
        }
    }
    
    if (isset($data['average'])) {
        $sanitized['average'] = floatval($data['average']);
    }
    
    if (isset($data['lastUpdated'])) {
        $sanitized['lastUpdated'] = sanitize_text_field($data['lastUpdated']);
    }
    
    return $sanitized;
}

function ratings_card_sanitize_rating($rating) {
    if (!is_array($rating)) {
        return array();
    }
    
    $sanitized = array();
    
    if (isset($rating['score'])) {
        $sanitized['score'] = floatval($rating['score']);
    }
    
    if (isset($rating['votes'])) {
        $sanitized['votes'] = absint($rating['votes']);
    }
    
    if (isset($rating['url'])) {
        $sanitized['url'] = esc_url_raw($rating['url']);
    }
    
    if (isset($rating['tomatometer'])) {
        $sanitized['tomatometer'] = floatval($rating['tomatometer']);
    }
    
    if (isset($rating['audience'])) {
        $sanitized['audience'] = floatval($rating['audience']);
    }
    
    return $sanitized;
}

