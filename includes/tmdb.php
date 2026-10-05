<?php
const TMDB_API = 'https://api.themoviedb.org/3';
const TMDB_IMAGES = 'https://image.tmdb.org/t/p/';

function tmdb_key()
{
    $key = getenv('TMDB_API_KEY');
    if (!$key && is_file(__DIR__ . '/secrets.php')) {
        $secrets = require __DIR__ . '/secrets.php';
        $key = is_array($secrets) ? ($secrets['tmdb_api_key'] ?? '') : '';
    }
    return trim((string) $key);
}

function tmdb_get($path, $params = [])
{
    $key = tmdb_key();
    if ($key === '') {
        throw new RuntimeException('No TMDB key. Put it in includes/secrets.php (copy includes/secrets.example.php).');
    }
    $headers = ['Accept: application/json'];
    if (strpos($key, 'eyJ') === 0) {
        $headers[] = 'Authorization: Bearer ' . $key;
    } else {
        $params['api_key'] = $key;
    }
    $url = TMDB_API . $path . ($params ? '?' . http_build_query($params) : '');

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($status === 429 || $status >= 500 || $body === false) {
            sleep($attempt);
            continue;
        }
        $data = json_decode($body, true);
        if ($status !== 200) {
            throw new RuntimeException('TMDB ' . $path . ' failed (' . $status . '): ' . ($data['status_message'] ?? $error ?: 'unknown error'));
        }
        return $data;
    }
    throw new RuntimeException('TMDB ' . $path . ' failed after 3 attempts: ' . ($error ?: 'HTTP ' . $status));
}

function tmdb_download($image_path, $size, $dest)
{
    if (!$image_path) {
        return false;
    }
    $ch = curl_init(TMDB_IMAGES . $size . $image_path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => true]);
    $bytes = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200 || !$bytes || strlen($bytes) < 1024) {
        return false;
    }
    return file_put_contents($dest, $bytes) !== false;
}

function tmdb_trailer_key($videos)
{
    $best = null;
    $best_score = -1;
    foreach ($videos['results'] ?? [] as $video) {
        if (($video['site'] ?? '') !== 'YouTube' || empty($video['key'])) {
            continue;
        }
        $score = (($video['type'] ?? '') === 'Trailer' ? 4 : (($video['type'] ?? '') === 'Teaser' ? 2 : 0))
            + (!empty($video['official']) ? 1 : 0);
        if ($score > $best_score) {
            $best = $video['key'];
            $best_score = $score;
        }
    }
    return $best;
}
