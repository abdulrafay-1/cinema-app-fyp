<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/booking.php';

const DEMO_DOMAIN = '@demo.example.com';
const DEMO_CUSTOMERS = [
    ['Ayesha Khan', 'ayesha.khan', '03001234561'],
    ['Hamza Ali', 'hamza.ali', '03011234562'],
    ['Fatima Noor', 'fatima.noor', '03121234563'],
    ['Usman Tariq', 'usman.tariq', '03211234564'],
    ['Zainab Hussain', 'zainab.hussain', '03331234565'],
    ['Ali Raza', 'ali.raza', '03451234566'],
    ['Mariam Siddiqui', 'mariam.siddiqui', '03061234567'],
    ['Saad Malik', 'saad.malik', '03151234568'],
];
const REVIEW_LINES = [
    [5, 'Loved it! Sound and picture were amazing, and booking the seats online took a minute.'],
    [4, 'Great movie, a little long in the middle. The Business seats are worth it.'],
    [5, 'Went with family, everyone enjoyed it. Popcorn combo was fresh too.'],
    [3, 'Decent one-time watch. The ending felt rushed.'],
    [4, 'Strong performances and great visuals. Would watch again.'],
];

function say($message)
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function pick($list)
{
    return $list[mt_rand(0, count($list) - 1)];
}

function free_seats($show)
{
    $taken = array_flip(array_column(db_all('SELECT seat_label FROM booking_seats WHERE show_id = ?', 'i', [(int) $show['show_id']]), 'seat_label'));
    $free = [];
    foreach (row_letters($show['total_rows']) as $row) {
        for ($n = 1; $n <= (int) $show['seats_per_row']; $n++) {
            if (!isset($taken[$row . $n])) {
                $free[] = $row . $n;
            }
        }
    }
    return $free;
}

function insert_booking($user, $show, $booked_at, $status, $refund = null)
{
    $prices = movie_prices($show['movie_name']);
    $free = free_seats($show);
    if (!$prices || count($free) < 4) {
        return null;
    }
    $row = pick(row_letters($show['total_rows']));
    $row_free = array_values(array_filter($free, function ($label) use ($row) { return $label[0] === $row; }));
    $count = min(count($row_free), mt_rand(1, 4));
    if ($count < 1) {
        return null;
    }
    $start = mt_rand(0, count($row_free) - $count);
    $labels = array_slice($row_free, $start, $count);

    $snack_qty = [];
    if (mt_rand(1, 10) <= 4) {
        $snacks = active_snacks();
        $snack_qty[(int) pick($snacks)['snack_id']] = mt_rand(1, 2);
    }
    $promo = mt_rand(1, 10) <= 2 ? db_one('SELECT * FROM promo_codes WHERE code = ?', 's', [pick(['EID20', 'FYP10'])]) : null;
    $t = checkout_totals($prices, $labels, $snack_qty, $promo);

    $method = pick(['Card', 'Card', 'JazzCash', 'Easypaisa']);
    $last4 = $method === 'Card' ? pick(['4242', '1881', '0005', '7710']) : substr($user['phone'], -4);
    $starts = $show['show_date'] . ' ' . $show['show_time'] . ':00';
    $refund_amount = 0;
    $refund_status = 'None';
    $cancelled_at = null;
    if ($status === 'Cancelled') {
        $cancelled_at = date('Y-m-d H:i:s', min(strtotime($booked_at) + 3600 * mt_rand(2, 20), time() - 3600));
        $refund_amount = (int) floor($t['total'] * ((strtotime($starts) - strtotime($cancelled_at)) >= 86400 ? 100 : 50) / 100);
        $refund_status = $refund;
    }

    db_query(
        "INSERT INTO booking_details
            (user_name, user_email, user_movie_dur, user_movie_cinema, user_movie_name, user_date, user_price, user_seat, user_card, user_movie_img,
             user_id, show_id, total_amount, snacks_amount, discount_amount, promo_code, payment_method, status, refund_status, refund_amount,
             refund_reason, refund_processed_at, cancelled_at, checked_in_at, booked_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        'ssssssssssiiiiissssisssss',
        [
            $user['name'], $user['email'], show_time_label($show['show_time']), $show['cinema_name'], $show['movie_name'],
            show_date_label($show['show_date']), seat_class_summary($t['seats']), implode(', ', $labels), $last4, $show['movie_img'],
            (int) $user['id'], (int) $show['show_id'], $t['total'], $t['snacks_amount'], $t['discount'], $t['promo_code'], $method,
            $status, $refund_status, $refund_amount,
            $refund_status === 'Rejected' ? 'Cancelled after the refund window shown at checkout.' : null,
            in_array($refund_status, ['Refunded', 'Rejected'], true) ? date('Y-m-d H:i:s', min(strtotime($cancelled_at) + 2400, time() - 300)) : null,
            $cancelled_at,
            $status === 'Used' ? date('Y-m-d H:i:s', strtotime($starts) - 60 * mt_rand(5, 25)) : null,
            $booked_at,
        ]
    );
    $booking_id = (int) mysqli_insert_id(db());
    db_query('UPDATE booking_details SET booking_ref = ? WHERE booking_id = ?', 'si', [generate_booking_ref($booking_id, date('Y', strtotime($booked_at))), $booking_id]);

    if ($status !== 'Cancelled') {
        foreach ($t['seats'] as $seat) {
            db_query('INSERT INTO booking_seats (booking_id, show_id, seat_label, seat_class, price) VALUES (?, ?, ?, ?, ?)', 'iissi', [$booking_id, (int) $show['show_id'], $seat['label'], $seat['class'], $seat['price']]);
        }
    }
    foreach ($t['snacks'] as $snack) {
        db_query('INSERT INTO booking_snacks (booking_id, snack_id, qty, price) VALUES (?, ?, ?, ?)', 'iiii', [$booking_id, $snack['snack_id'], $snack['qty'], $snack['price']]);
    }
    return ['id' => $booking_id, 'total' => $t['total'], 'movie_id' => (int) $show['movie_id'], 'status' => $status, 'refund' => $refund_status];
}

function show_row($show_id)
{
    return db_one(
        'SELECT s.*, m.movie_name, m.movie_img, c.cinema_name FROM shows s JOIN addmovie m ON m.movie_id = s.movie_id JOIN cinema c ON c.cin_id = s.cin_id WHERE s.show_id = ?',
        'i',
        [(int) $show_id]
    );
}

mt_srand(2026);
$db = db();
if (!db_one('SELECT 1 FROM addmovie LIMIT 1')) {
    fwrite(STDERR, "ERROR: no movies. Run database/seed_tmdb.php --yes first.\n");
    exit(1);
}

mysqli_begin_transaction($db);
try {
    $old_users = array_column(db_all('SELECT id FROM user_reg_login WHERE email LIKE ?', 's', ['%' . DEMO_DOMAIN]), 'id');
    if ($old_users) {
        $ids = implode(',', array_map('intval', $old_users));
        $old_rows = db_all("SELECT booking_id, booking_ref FROM booking_details WHERE user_id IN ($ids)");
        $old_bookings = array_column($old_rows, 'booking_id');
        foreach (array_column($old_rows, 'booking_ref') as $old_ref) {
            db_query("DELETE FROM activity_log WHERE action IN ('Checked in ticket', 'Approved refund', 'Rejected refund') AND details LIKE ?", 's', [$old_ref . '%']);
        }
        if ($old_bookings) {
            $bids = implode(',', array_map('intval', $old_bookings));
            db_query("DELETE FROM booking_seats WHERE booking_id IN ($bids)");
            db_query("DELETE FROM booking_snacks WHERE booking_id IN ($bids)");
            db_query("DELETE FROM booking_details WHERE booking_id IN ($bids)");
        }
        db_query("DELETE FROM reviews WHERE user_id IN ($ids)");
        db_query("DELETE FROM favorites WHERE user_id IN ($ids)");
        db_query("DELETE FROM seat_holds WHERE user_id IN ($ids)");
        db_query("DELETE FROM user_reg_login WHERE id IN ($ids)");
    }
    db_query('DELETE s FROM shows s WHERE s.show_date < CURDATE() AND NOT EXISTS (SELECT 1 FROM booking_details b WHERE b.show_id = s.show_id)');

    $users = [];
    foreach (DEMO_CUSTOMERS as $i => $c) {
        db_query(
            'INSERT INTO user_reg_login (name, email, password, phone, status, created_at) VALUES (?, ?, ?, ?, ?, NOW() - INTERVAL ? DAY)',
            'sssssi',
            [$c[0], $c[1] . DEMO_DOMAIN, '123', $c[2], $i === 7 ? 'Blocked' : 'Active', 30 - $i * 3]
        );
        $users[] = db_one('SELECT id, name, email, phone FROM user_reg_login WHERE id = ?', 'i', [(int) mysqli_insert_id($db)]);
    }
    $active = array_slice($users, 0, 7);

    $movies = db_all('SELECT movie_id, movie_cin_id FROM addmovie ORDER BY popularity DESC LIMIT 6');
    $cinemas = array_column(db_all('SELECT cin_id FROM cinema ORDER BY cin_id'), 'cin_id');
    $past_shows = [];
    for ($day = 1; $day <= 6; $day++) {
        foreach ([['15:45', 0], ['21:15', 3]] as $slot) {
            $movie = $movies[($day + $slot[1]) % count($movies)];
            db_query(
                "INSERT INTO shows (movie_id, cin_id, show_date, show_time, hall_name, total_rows, seats_per_row, status) VALUES (?, ?, CURDATE() - INTERVAL ? DAY, ?, ?, 8, 12, 'Active')",
                'iiiss',
                [(int) $movie['movie_id'], (int) $cinemas[($day + $slot[1]) % count($cinemas)], $day, $slot[0], 'Hall ' . (1 + $slot[1] % 3)]
            );
            $past_shows[] = ['id' => (int) mysqli_insert_id($db), 'day' => $day, 'time' => $slot[0]];
        }
    }
    $upcoming = array_column(db_all(
        "SELECT show_id FROM shows WHERE status = 'Active' AND TIMESTAMP(show_date, show_time) > NOW() + INTERVAL 3 HOUR ORDER BY show_date, show_time"
    ), 'show_id');

    $made = [];
    foreach ($past_shows as $i => $ps) {
        if ($i % 6 === 5) {
            continue;
        }
        $show = show_row($ps['id']);
        $booked_at = date('Y-m-d H:i:s', strtotime($show['show_date'] . ' ' . $show['show_time']) - 3600 * mt_rand(5, 40));
        $made[] = insert_booking($active[$i % 7], $show, $booked_at, $i % 5 === 4 ? 'Booked' : 'Used');
    }
    for ($i = 0; $i < 10 && $upcoming; $i++) {
        $show = show_row($upcoming[($i * 7) % count($upcoming)]);
        $booked_at = date('Y-m-d H:i:s', strtotime('-' . mt_rand(0, 5) . ' days -' . mt_rand(1, 10) . ' hours'));
        $made[] = insert_booking($active[($i + 2) % 7], $show, $booked_at, 'Booked');
    }
    foreach (['Pending', 'Pending', 'Refunded', 'Rejected'] as $i => $refund) {
        $show = show_row($upcoming[($i * 11 + 3) % count($upcoming)]);
        $booked_at = date('Y-m-d H:i:s', strtotime('-' . ($i + 1) . ' days -' . mt_rand(2, 9) . ' hours'));
        $made[] = insert_booking($active[($i + 4) % 7], $show, $booked_at, 'Cancelled', $refund);
    }
    $made = array_values(array_filter($made));

    $reviewed = [];
    $review_i = 0;
    foreach ($made as $k => $b) {
        if ($b['status'] !== 'Used' || isset($reviewed[$b['movie_id']]) || $review_i >= count(REVIEW_LINES)) {
            continue;
        }
        $user_id = (int) db_one('SELECT user_id FROM booking_details WHERE booking_id = ?', 'i', [$b['id']])['user_id'];
        db_query(
            "INSERT INTO reviews (movie_id, user_id, rating, comment, status, created_at) VALUES (?, ?, ?, ?, 'Visible', NOW() - INTERVAL ? HOUR)",
            'iiisi',
            [$b['movie_id'], $user_id, REVIEW_LINES[$review_i][0], REVIEW_LINES[$review_i][1], 6 + $review_i * 9]
        );
        $reviewed[$b['movie_id']] = true;
        $review_i++;
    }
    foreach (array_slice($active, 0, 4) as $i => $u) {
        db_query('INSERT IGNORE INTO favorites (user_id, movie_id) VALUES (?, ?)', 'ii', [(int) $u['id'], (int) $movies[$i]['movie_id']]);
    }

    $admin_email = db_one('SELECT email FROM admin_login ORDER BY id LIMIT 1')['email'] ?? 'admin';
    foreach ($made as $b) {
        $ref = db_one('SELECT booking_ref, checked_in_at, refund_processed_at, refund_amount FROM booking_details WHERE booking_id = ?', 'i', [$b['id']]);
        if ($b['status'] === 'Used') {
            db_query("INSERT INTO activity_log (admin_email, action, details, created_at) VALUES (?, 'Checked in ticket', ?, ?)", 'sss', [$admin_email, $ref['booking_ref'], $ref['checked_in_at']]);
        } elseif (in_array($b['refund'], ['Refunded', 'Rejected'], true)) {
            db_query(
                "INSERT INTO activity_log (admin_email, action, details, created_at) VALUES (?, ?, ?, ?)",
                'ssss',
                [$admin_email, $b['refund'] === 'Refunded' ? 'Approved refund' : 'Rejected refund', $ref['booking_ref'] . ' ' . money($ref['refund_amount']), $ref['refund_processed_at']]
            );
        }
    }

    db_query("UPDATE promo_codes p SET used_count = (SELECT COUNT(*) FROM booking_details b WHERE b.promo_code = p.code)");
    db_query("UPDATE user_reg_login u SET loyalty_points = (SELECT COALESCE(SUM(FLOOR(b.total_amount / 100)), 0) FROM booking_details b WHERE b.user_id = u.id AND b.status <> 'Cancelled')");

    mysqli_commit($db);
} catch (Throwable $e) {
    mysqli_rollback($db);
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . " Nothing was changed.\n");
    exit(1);
}

$counts = array_count_values(array_map(function ($b) { return $b['status'] . ($b['refund'] !== 'None' ? '/' . $b['refund'] : ''); }, $made));
ksort($counts);
say(count($users) . ' demo customers (password 123, one Blocked), ' . count($past_shows) . ' past shows, ' . count($made) . ' bookings:');
foreach ($counts as $k => $v) {
    say('  ' . $k . ': ' . $v);
}
say($review_i . ' reviews, ' . min(4, count($active)) . ' favorites. Re-run any time; it replaces only its own demo data.');
