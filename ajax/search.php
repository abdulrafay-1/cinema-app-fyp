<?php
require_once __DIR__ . '/../includes/functions.php';

$q = trim(isset($_GET['q']) ? (string) $_GET['q'] : '');
if (mb_strlen($q) < 2) {
    json_out(['ok' => true, 'results' => []]);
}

$like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
$rows = db_all(
    "SELECT m.movie_id, m.movie_name, m.movie_img, g.genre_name, i.industry_name
     FROM addmovie m
     LEFT JOIN genre g ON g.gen_id = m.movie_genre_id
     LEFT JOIN industry i ON i.indus_id = m.movie_indus_id
     WHERE m.movie_name LIKE ?
     ORDER BY m.movie_name LIKE ? DESC, m.movie_name
     LIMIT 6",
    'ss',
    [$like, str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%']
);

json_out(['ok' => true, 'results' => array_map(function ($r) {
    return [
        'id' => (int) $r['movie_id'],
        'name' => trim($r['movie_name']),
        'poster' => 'Admin/upload-images/' . $r['movie_img'],
        'meta' => trim(($r['genre_name'] ?: '') . ' · ' . ($r['industry_name'] ?: ''), ' ·'),
    ];
}, $rows)]);
