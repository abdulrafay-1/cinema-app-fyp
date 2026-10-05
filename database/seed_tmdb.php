<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/booking.php';
require_once __DIR__ . '/../includes/tmdb.php';

const NOW_SHOWING_HOLLYWOOD = 16;
const NOW_SHOWING_BOLLYWOOD = 8;
const NOW_SHOWING_LOLLYWOOD = 6;
const COMING_SOON = 8;
const SHOW_TIMES = ['13:00', '15:45', '18:30', '21:15', '23:30'];
const INDUSTRY_BY_LANGUAGE = [
    'en' => 'HollyWood', 'hi' => 'BollyWood', 'ur' => 'LollyWood', 'pa' => 'LollyWood',
    'ta' => 'Tamil', 'te' => 'Tollywood', 'ml' => 'Mollywood', 'zh' => 'Chinese', 'cn' => 'Chinese',
    'pt' => 'Portuguese', 'ko' => 'Korean', 'ja' => 'Japanese',
];

$upload_dir = realpath(__DIR__ . '/../Admin/upload-images');

function say($message)
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail($message)
{
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit(1);
}

function lookup_id($table, $id_col, $name_col, $name)
{
    $name = trim($name);
    $row = db_one("SELECT $id_col AS id FROM $table WHERE LOWER(TRIM($name_col)) = LOWER(?) LIMIT 1", 's', [$name]);
    if ($row) {
        return (int) $row['id'];
    }
    if (db_query("INSERT INTO $table ($name_col) VALUES (?)", 's', [$name]) === false) {
        throw new RuntimeException("Could not add $name to $table: " . mysqli_error(db()));
    }
    return (int) mysqli_insert_id(db());
}

function collect($path, $params, $limit, $pages, $keep, &$seen)
{
    $picked = [];
    for ($page = 1; $page <= $pages && count($picked) < $limit; $page++) {
        $data = tmdb_get($path, $params + ['page' => $page]);
        foreach ($data['results'] ?? [] as $movie) {
            if (count($picked) >= $limit) {
                break;
            }
            if (!empty($movie['adult']) || empty($movie['poster_path']) || isset($seen[$movie['id']]) || !$keep($movie)) {
                continue;
            }
            $seen[$movie['id']] = true;
            $picked[] = $movie;
        }
        if ($page >= (int) ($data['total_pages'] ?? 1)) {
            break;
        }
    }
    return $picked;
}

if (!in_array('--yes', $argv, true)) {
    say('This replaces ALL movies, shows, prices, coming soon, bookings, refunds, reviews and favorites with TMDB data.');
    say('Users, admins, cinemas, snacks and promo codes are kept (promo usage and loyalty points are reset).');
    say('Back up first:  docker compose exec -T db mysqldump -uroot dbmo > backup.sql');
    say('Then run:       docker compose exec web php database/seed_tmdb.php --yes');
    exit(0);
}
if (!$upload_dir || !is_writable($upload_dir)) {
    fail('Admin/upload-images is missing or not writable.');
}
if (!db_one("SHOW COLUMNS FROM addmovie LIKE 'tmdb_id'")) {
    fail('Run database/migrations/005_rafay_tmdb.sql first.');
}
if (!db_one('SELECT cin_id FROM cinema LIMIT 1')) {
    fail('Add at least one cinema first (Admin > Cinema).');
}

try {
    $genres = array_column(tmdb_get('/genre/movie/list', ['language' => 'en'])['genres'] ?? [], 'name', 'id');
    say('Connected to TMDB. ' . count($genres) . ' genres.');

    $today = date('Y-m-d');
    $seen = [];
    $groups = [
        'Hollywood' => collect('/movie/now_playing', ['language' => 'en-US', 'region' => 'US'], NOW_SHOWING_HOLLYWOOD, 3,
            function ($m) { return ($m['original_language'] ?? '') === 'en'; }, $seen),
        'Bollywood' => collect('/discover/movie', [
            'with_original_language' => 'hi', 'sort_by' => 'popularity.desc', 'include_adult' => 'false',
            'primary_release_date.gte' => date('Y-m-d', strtotime('-150 days')), 'primary_release_date.lte' => $today,
        ], NOW_SHOWING_BOLLYWOOD, 2, function () { return true; }, $seen),
        'Lollywood' => collect('/discover/movie', [
            'with_origin_country' => 'PK', 'sort_by' => 'popularity.desc', 'include_adult' => 'false',
            'primary_release_date.gte' => date('Y-m-d', strtotime('-3 years')), 'primary_release_date.lte' => $today,
        ], NOW_SHOWING_LOLLYWOOD, 2, function () { return true; }, $seen),
    ];
    $upcoming = collect('/movie/upcoming', ['language' => 'en-US', 'region' => 'US'], COMING_SOON, 3,
        function ($m) use ($today) { return ($m['release_date'] ?? '') > $today; }, $seen);
    foreach ($groups as $name => $list) {
        say(sprintf('%-10s %d movies', $name, count($list)));
    }
    say(sprintf('%-10s %d movies', 'Upcoming', count($upcoming)));

    $movies = [];
    foreach (array_merge(...array_values($groups)) as $basic) {
        $d = tmdb_get('/movie/' . (int) $basic['id'], ['language' => 'en-US', 'append_to_response' => 'videos']);
        $poster = 'tmdb_' . (int) $d['id'] . '.jpg';
        if (!tmdb_download($d['poster_path'] ?? $basic['poster_path'], 'w500', $upload_dir . '/' . $poster)) {
            say('  skip (poster download failed): ' . $d['title']);
            continue;
        }
        $backdrop = 'tmdb_' . (int) $d['id'] . '_bg.jpg';
        if (!tmdb_download($d['backdrop_path'] ?? null, 'w1280', $upload_dir . '/' . $backdrop)) {
            $backdrop = null;
        }
        $movies[] = ['d' => $d, 'poster' => $poster, 'backdrop' => $backdrop, 'trailer' => tmdb_trailer_key($d['videos'] ?? [])];
        say('  + ' . $d['title'] . ' (' . ($d['original_language'] ?? '?') . ', ' . ($d['release_date'] ?? '?') . ')');
        usleep(120000);
    }

    $soon = [];
    foreach ($upcoming as $basic) {
        $d = tmdb_get('/movie/' . (int) $basic['id'], ['language' => 'en-US', 'append_to_response' => 'videos']);
        $poster = 'tmdb_' . (int) $d['id'] . '.jpg';
        if (!tmdb_download($d['poster_path'] ?? $basic['poster_path'], 'w500', $upload_dir . '/' . $poster)) {
            continue;
        }
        $soon[] = ['d' => $d, 'poster' => $poster, 'trailer' => tmdb_trailer_key($d['videos'] ?? [])];
        say('  ~ ' . $d['title'] . ' (coming ' . ($d['release_date'] ?? '?') . ')');
        usleep(120000);
    }
} catch (RuntimeException $e) {
    fail($e->getMessage() . ' Nothing in the database was changed.');
}

if (count($movies) < 3) {
    fail('Only ' . count($movies) . ' movies downloaded, refusing to replace the catalog. Nothing in the database was changed.');
}

usort($movies, function ($a, $b) { return ($b['d']['popularity'] ?? 0) <=> ($a['d']['popularity'] ?? 0); });
$cinemas = array_column(db_all('SELECT cin_id FROM cinema ORDER BY cin_id'), 'cin_id');

$db = db();
mysqli_begin_transaction($db);
try {
    foreach (['seat_holds', 'booking_seats', 'booking_snacks', 'booking_details', 'reviews', 'favorites', 'shows', 'movie_price', 'coming_soon', 'addmovie'] as $table) {
        if (db_query("DELETE FROM $table") === false) {
            throw new RuntimeException("Could not clear $table: " . mysqli_error($db));
        }
    }
    db_query('UPDATE promo_codes SET used_count = 0');
    db_query('UPDATE user_reg_login SET loyalty_points = 0');

    foreach ($movies as $rank => $m) {
        $d = $m['d'];
        $lang_code = $d['original_language'] ?? 'en';
        $lang_name = strtoupper($lang_code);
        foreach ($d['spoken_languages'] ?? [] as $spoken) {
            if (($spoken['iso_639_1'] ?? '') === $lang_code) {
                $lang_name = $spoken['english_name'] ?: $lang_name;
            }
        }
        $origin = $d['origin_country'] ?? [];
        $industry = in_array('PK', $origin, true) ? 'LollyWood' : (INDUSTRY_BY_LANGUAGE[$lang_code] ?? 'International');
        $genre_name = $d['genres'][0]['name'] ?? 'Drama';
        $release = $d['release_date'] ?: $today;
        $runtime = (int) ($d['runtime'] ?? 0);
        $first_cinema = (int) $cinemas[$rank % count($cinemas)];

        $ok = db_query(
            'INSERT INTO addmovie (movie_name, movie_cin_id, movie_genre_id, movie_indus_id, movie_lang_id, movie_date_id, movie_img, movie_trl_url,
                                   movie_duration, description, tmdb_id, backdrop, release_date, rating, runtime, popularity)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'siiiiissssisssid',
            [
                mb_substr($d['title'], 0, 200), $first_cinema,
                lookup_id('genre', 'gen_id', 'genre_name', $genre_name),
                lookup_id('industry', 'indus_id', 'industry_name', $industry),
                lookup_id('language', 'lang_id', 'language_name', $lang_name),
                lookup_id('date', 'date_id', 'date_name', $release),
                $m['poster'], $m['trailer'] ? 'https://www.youtube.com/embed/' . $m['trailer'] : '',
                $runtime ? intdiv($runtime, 60) . 'h ' . ($runtime % 60) . 'm' : 'TBA',
                $d['overview'] ?: null, (int) $d['id'], $m['backdrop'], $release,
                round((float) ($d['vote_average'] ?? 0), 1), $runtime ?: null, (float) ($d['popularity'] ?? 0),
            ]
        );
        if (!$ok) {
            throw new RuntimeException('Could not insert ' . $d['title'] . ': ' . mysqli_error($db));
        }
        $movie_id = (int) mysqli_insert_id($db);

        $tier = $rank < 5 ? [1500, 2100, 2900] : ($rank < 15 ? [1200, 1700, 2400] : [1000, 1400, 2000]);
        db_query(
            'INSERT INTO movie_price (movie_name_price, economy, business, first_class) VALUES (?, ?, ?, ?)',
            'ssss',
            [mb_substr($d['title'], 0, 200), (string) $tier[0], (string) $tier[1], (string) $tier[2]]
        );

        $show_count = $rank < 10 ? 4 : 3;
        for ($k = 0; $k < $show_count; $k++) {
            $day = ($rank + $k * 2) % 7 + ($k === 0 ? 0 : 1);
            $time = SHOW_TIMES[($rank + $k) % count(SHOW_TIMES)];
            if ($day === 0 && strtotime($today . ' ' . $time) < time() + 3600) {
                $day = 1;
            }
            db_query(
                "INSERT INTO shows (movie_id, cin_id, show_date, show_time, hall_name, total_rows, seats_per_row, status)
                 VALUES (?, ?, CURDATE() + INTERVAL ? DAY, ?, ?, 8, 12, 'Active')",
                'iiiss',
                [$movie_id, (int) $cinemas[($rank + $k * 3) % count($cinemas)], $day, $time, 'Hall ' . ($k + 1)]
            );
        }
    }

    foreach ($soon as $s) {
        $d = $s['d'];
        db_query(
            'INSERT INTO coming_soon (title, poster, trailer_url, release_date, description, tmdb_id) VALUES (?, ?, ?, ?, ?, ?)',
            'sssssi',
            [
                mb_substr($d['title'], 0, 200), $s['poster'],
                $s['trailer'] ? 'https://www.youtube.com/watch?v=' . $s['trailer'] : null,
                $d['release_date'] ?: null, $d['overview'] ?: null, (int) $d['id'],
            ]
        );
    }

    db_query(
        'INSERT INTO activity_log (admin_email, action, details) VALUES (?, ?, ?)',
        'sss',
        ['cli', 'Seeded TMDB catalog', count($movies) . ' movies, ' . count($soon) . ' coming soon']
    );
    mysqli_commit($db);
} catch (Throwable $e) {
    mysqli_rollback($db);
    fail($e->getMessage() . ' All database changes were rolled back.');
}

foreach (['booking_details', 'booking_seats', 'booking_snacks', 'seat_holds'] as $table) {
    db_query("ALTER TABLE $table AUTO_INCREMENT = 1");
}

$keep = array_flip(array_merge(
    array_column($movies, 'poster'),
    array_filter(array_column($movies, 'backdrop')),
    array_column($soon, 'poster')
));
$removed = 0;
foreach (glob($upload_dir . '/tmdb_*') as $file) {
    if (!isset($keep[basename($file)]) && unlink($file)) {
        $removed++;
    }
}

$stats = db_one("SELECT (SELECT COUNT(*) FROM addmovie) AS movies, (SELECT COUNT(*) FROM shows) AS shows, (SELECT COUNT(*) FROM coming_soon) AS soon");
say(sprintf('Done: %d movies, %d shows, %d coming soon. Removed %d stale TMDB images.', $stats['movies'], $stats['shows'], $stats['soon'], $removed));
