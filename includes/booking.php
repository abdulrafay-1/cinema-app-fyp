<?php
require_once __DIR__ . '/functions.php';

const HOLD_MINUTES = 5;
const MAX_SEATS = 10;
const SEAT_CLASSES = ['Economy', 'Business', 'First'];
const TICKETS_SQL = "(CASE WHEN b.show_id IS NULL THEN IF(b.user_seat REGEXP '^[0-9]+$', CAST(b.user_seat AS UNSIGNED), 0) ELSE LENGTH(b.user_seat) - LENGTH(REPLACE(b.user_seat, ',', '')) + 1 END)";
const REFUNDED_SQL = "(CASE WHEN b.refund_status = 'Refunded' THEN b.refund_amount ELSE 0 END)";

function seat_class($row_letter)
{
    $index = ord(strtoupper($row_letter)) - 65;
    if ($index < 3) {
        return 'Economy';
    }
    return $index < 6 ? 'Business' : 'First';
}

function row_letters($total_rows)
{
    return array_slice(range('A', 'Z'), 0, max(1, min(26, (int) $total_rows)));
}

function valid_seat_label($show, $label)
{
    if (!preg_match('/^([A-Z])([0-9]{1,2})$/', $label, $m)) {
        return false;
    }
    return in_array($m[1], row_letters($show['total_rows']), true)
        && (int) $m[2] >= 1 && (int) $m[2] <= (int) $show['seats_per_row'];
}

function show_time_label($time)
{
    return date('g:i A', strtotime($time));
}

function show_date_label($date)
{
    return date('D, d M Y', strtotime($date));
}

function get_show($show_id)
{
    return db_one(
        "SELECT s.*, m.movie_name, m.movie_img, m.backdrop, m.description, c.cinema_name, c.cinema_location,
                TIMESTAMP(s.show_date, s.show_time) AS starts_at,
                TIMESTAMP(s.show_date, s.show_time) > NOW() AS is_upcoming
         FROM shows s
         JOIN addmovie m ON m.movie_id = s.movie_id
         JOIN cinema c ON c.cin_id = s.cin_id
         WHERE s.show_id = ?",
        'i',
        [(int) $show_id]
    );
}

function bookable_show($show_id)
{
    $show = get_show($show_id);
    return ($show && $show['status'] === 'Active' && $show['is_upcoming']) ? $show : null;
}

function movie_prices($movie_name)
{
    $row = db_one(
        'SELECT economy, business, first_class FROM movie_price WHERE TRIM(movie_name_price) = TRIM(?) ORDER BY price_id DESC LIMIT 1',
        's',
        [$movie_name]
    );
    if (!$row) {
        return null;
    }
    return ['Economy' => (int) $row['economy'], 'Business' => (int) $row['business'], 'First' => (int) $row['first_class']];
}

function upcoming_shows($movie_id, $days = 7)
{
    return db_all(
        "SELECT s.*, c.cinema_name, c.cinema_location,
                s.total_rows * s.seats_per_row - (SELECT COUNT(*) FROM booking_seats b WHERE b.show_id = s.show_id) AS seats_left
         FROM shows s
         JOIN cinema c ON c.cin_id = s.cin_id
         WHERE s.movie_id = ? AND s.status = 'Active'
           AND TIMESTAMP(s.show_date, s.show_time) > NOW()
           AND s.show_date <= CURDATE() + INTERVAL ? DAY
         ORDER BY s.show_date, s.show_time, c.cinema_name",
        'ii',
        [(int) $movie_id, (int) $days]
    );
}

function release_expired_holds()
{
    db_query('DELETE FROM seat_holds WHERE expires_at <= NOW()');
}

function seat_states($show_id, $user_id)
{
    release_expired_holds();
    $booked = array_column(db_all('SELECT seat_label FROM booking_seats WHERE show_id = ?', 'i', [(int) $show_id]), 'seat_label');
    $held = [];
    $mine = [];
    foreach (db_all('SELECT seat_label, user_id FROM seat_holds WHERE show_id = ?', 'i', [(int) $show_id]) as $hold) {
        if ((int) $hold['user_id'] === (int) $user_id) {
            $mine[] = $hold['seat_label'];
        } else {
            $held[] = $hold['seat_label'];
        }
    }
    return ['booked' => $booked, 'held' => $held, 'mine' => $mine];
}

function hold_seats($show_id, $user_id, $labels)
{
    release_expired_holds();
    mysqli_begin_transaction(db());
    db_query('DELETE FROM seat_holds WHERE user_id = ?', 'i', [(int) $user_id]);
    $conflicts = [];
    foreach ($labels as $label) {
        $booked = db_one('SELECT 1 FROM booking_seats WHERE show_id = ? AND seat_label = ?', 'is', [(int) $show_id, $label]);
        $inserted = $booked ? false : db_query(
            'INSERT INTO seat_holds (show_id, seat_label, user_id, expires_at) VALUES (?, ?, ?, NOW() + INTERVAL ' . HOLD_MINUTES . ' MINUTE)',
            'isi',
            [(int) $show_id, $label, (int) $user_id]
        );
        if (!$inserted) {
            $conflicts[] = $label;
        }
    }
    if ($conflicts) {
        mysqli_rollback(db());
        return $conflicts;
    }
    mysqli_commit(db());
    return [];
}

function release_user_holds($user_id)
{
    db_query('DELETE FROM seat_holds WHERE user_id = ?', 'i', [(int) $user_id]);
}

function user_holds($user_id, $show_id)
{
    release_expired_holds();
    return db_all(
        'SELECT seat_label, TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS seconds_left
         FROM seat_holds WHERE user_id = ? AND show_id = ? ORDER BY LEFT(seat_label, 1), CAST(SUBSTRING(seat_label, 2) AS UNSIGNED)',
        'ii',
        [(int) $user_id, (int) $show_id]
    );
}

function priced_seats($labels, $prices)
{
    $seats = [];
    foreach ($labels as $label) {
        $class = seat_class($label[0]);
        $seats[] = ['label' => $label, 'class' => $class, 'price' => $prices[$class]];
    }
    return $seats;
}

function booking_css()
{
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    $base = (strpos($_SERVER['SCRIPT_NAME'], '/Admin/') !== false) ? '../' : '';
    return '<link rel="stylesheet" href="' . $base . 'assets/booking.css?v=2">';
}

function active_snacks()
{
    return db_all('SELECT snack_id, name, price, image FROM snacks WHERE is_active = 1 ORDER BY price');
}

function snack_icon($name)
{
    $name = strtolower($name);
    foreach (['popcorn' => '🍿', 'nacho' => '🧀', 'drink' => '🥤', 'hot dog' => '🌭', 'coffee' => '☕', 'water' => '💧'] as $word => $icon) {
        if (strpos($name, $word) !== false) {
            return $icon;
        }
    }
    return '🍽️';
}

function find_promo($code, $lock = false)
{
    $code = strtoupper(trim((string) $code));
    if ($code === '') {
        return [null, 'Please enter a promo code.'];
    }
    $promo = db_one('SELECT * FROM promo_codes WHERE code = ?' . ($lock ? ' FOR UPDATE' : ''), 's', [$code]);
    if (!$promo || !(int) $promo['is_active']) {
        return [null, 'This promo code is not valid.'];
    }
    if ($promo['valid_until'] !== null && $promo['valid_until'] < date('Y-m-d')) {
        return [null, 'This promo code has expired.'];
    }
    if ((int) $promo['max_uses'] > 0 && (int) $promo['used_count'] >= (int) $promo['max_uses']) {
        return [null, 'This promo code has reached its usage limit.'];
    }
    return [$promo, null];
}

function clean_snack_qty($input)
{
    $qty = [];
    $valid = array_column(active_snacks(), null, 'snack_id');
    foreach ((array) $input as $snack_id => $count) {
        $count = max(0, min(10, (int) $count));
        if ($count > 0 && isset($valid[(int) $snack_id])) {
            $qty[(int) $snack_id] = $count;
        }
    }
    return $qty;
}

function checkout_totals($prices, $seat_labels, $snack_qty, $promo)
{
    $seats = priced_seats($seat_labels, $prices);
    $tickets = array_sum(array_column($seats, 'price'));
    $snacks = [];
    $snacks_amount = 0;
    if ($snack_qty) {
        foreach (active_snacks() as $snack) {
            $qty = $snack_qty[(int) $snack['snack_id']] ?? 0;
            if ($qty > 0) {
                $line = (int) $snack['price'] * $qty;
                $snacks[] = ['snack_id' => (int) $snack['snack_id'], 'name' => $snack['name'], 'qty' => $qty, 'price' => (int) $snack['price'], 'line' => $line];
                $snacks_amount += $line;
            }
        }
    }
    $subtotal = $tickets + $snacks_amount;
    $discount = $promo ? (int) floor($subtotal * (int) $promo['discount_percent'] / 100) : 0;
    return [
        'seats' => $seats,
        'tickets_amount' => $tickets,
        'snacks' => $snacks,
        'snacks_amount' => $snacks_amount,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'promo_code' => $promo ? $promo['code'] : null,
        'promo_percent' => $promo ? (int) $promo['discount_percent'] : 0,
        'total' => $subtotal - $discount,
    ];
}

function loyalty_points_for($amount)
{
    return (int) floor((int) $amount / 100);
}

function seat_class_summary($seats)
{
    $counts = array_count_values(array_column($seats, 'class'));
    $parts = [];
    foreach (SEAT_CLASSES as $class) {
        if (!empty($counts[$class])) {
            $parts[] = $class . ' x' . $counts[$class];
        }
    }
    return implode(', ', $parts);
}

function current_user_row()
{
    $id = current_user_id();
    return $id ? db_one('SELECT id, name, email, status, loyalty_points FROM user_reg_login WHERE id = ?', 'i', [$id]) : null;
}

function load_checkout($user_id, $show_id)
{
    $show = bookable_show($show_id);
    $holds = $show ? user_holds($user_id, $show['show_id']) : [];
    $prices = $show ? movie_prices($show['movie_name']) : null;
    if (!$holds || !$prices) {
        return null;
    }
    $state = $_SESSION['checkout'] ?? null;
    if (!$state || (int) $state['show_id'] !== (int) $show['show_id']) {
        $state = ['show_id' => (int) $show['show_id'], 'snacks' => [], 'promo' => null];
    }
    $promo = null;
    if ($state['promo']) {
        list($promo) = find_promo($state['promo']);
        if (!$promo) {
            $state['promo'] = null;
        }
    }
    $_SESSION['checkout'] = $state;
    return [
        'show' => $show,
        'prices' => $prices,
        'state' => $state,
        'seconds_left' => (int) min(array_column($holds, 'seconds_left')),
        'totals' => checkout_totals($prices, array_column($holds, 'seat_label'), $state['snacks'], $promo),
    ];
}

function finalize_booking($user, $show, $snack_qty, $promo_code, $method, $account_last4)
{
    $db = db();
    mysqli_begin_transaction($db);
    $fail = function ($message) use ($db) {
        mysqli_rollback($db);
        return ['ok' => false, 'message' => $message];
    };

    $holds = db_all(
        'SELECT seat_label FROM seat_holds WHERE user_id = ? AND show_id = ? AND expires_at > NOW() FOR UPDATE',
        'ii',
        [(int) $user['id'], (int) $show['show_id']]
    );
    if (!$holds) {
        return $fail('Your seat hold has expired. Please select your seats again.');
    }
    $prices = movie_prices($show['movie_name']);
    if (!$prices) {
        return $fail('Ticket prices are not set for this movie.');
    }

    $promo = null;
    if ($promo_code) {
        list($promo, $promo_error) = find_promo($promo_code, true);
        if (!$promo) {
            return $fail($promo_error . ' Please go back to checkout and remove it.');
        }
    }

    $labels = array_column($holds, 'seat_label');
    sort($labels, SORT_NATURAL);
    $t = checkout_totals($prices, $labels, $snack_qty, $promo);

    $inserted = db_query(
        "INSERT INTO booking_details
            (user_name, user_email, user_movie_dur, user_movie_cinema, user_movie_name, user_date, user_price, user_seat, user_card, user_movie_img,
             user_id, show_id, total_amount, snacks_amount, discount_amount, promo_code, payment_method, status, refund_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Booked', 'None')",
        'ssssssssssiiiiiss',
        [
            $user['name'], $user['email'], show_time_label($show['show_time']), $show['cinema_name'],
            $show['movie_name'], show_date_label($show['show_date']), seat_class_summary($t['seats']), implode(', ', $labels),
            $account_last4, $show['movie_img'], (int) $user['id'], (int) $show['show_id'], $t['total'], $t['snacks_amount'],
            $t['discount'], $t['promo_code'], $method,
        ]
    );
    if (!$inserted) {
        return $fail('Could not save your booking. Please try again.');
    }
    $booking_id = mysqli_insert_id($db);
    $ref = generate_booking_ref($booking_id);
    db_query('UPDATE booking_details SET booking_ref = ? WHERE booking_id = ?', 'si', [$ref, $booking_id]);

    foreach ($t['seats'] as $seat) {
        $ok = db_query(
            'INSERT INTO booking_seats (booking_id, show_id, seat_label, seat_class, price) VALUES (?, ?, ?, ?, ?)',
            'iissi',
            [$booking_id, (int) $show['show_id'], $seat['label'], $seat['class'], $seat['price']]
        );
        if (!$ok) {
            return $fail('Seat ' . $seat['label'] . ' was just booked by someone else. Please select your seats again.');
        }
    }
    foreach ($t['snacks'] as $snack) {
        db_query(
            'INSERT INTO booking_snacks (booking_id, snack_id, qty, price) VALUES (?, ?, ?, ?)',
            'iiii',
            [$booking_id, $snack['snack_id'], $snack['qty'], $snack['price']]
        );
    }
    if ($promo) {
        db_query('UPDATE promo_codes SET used_count = used_count + 1 WHERE promo_id = ?', 'i', [(int) $promo['promo_id']]);
    }
    db_query('UPDATE user_reg_login SET loyalty_points = loyalty_points + ? WHERE id = ?', 'ii', [loyalty_points_for($t['total']), (int) $user['id']]);
    db_query('DELETE FROM seat_holds WHERE user_id = ?', 'i', [(int) $user['id']]);

    if (!mysqli_commit($db)) {
        return $fail('Could not complete your booking. Please try again.');
    }
    return ['ok' => true, 'booking_id' => $booking_id, 'booking_ref' => $ref];
}

function load_booking($ref)
{
    $booking = db_one(
        "SELECT b.*, s.show_date, s.show_time, s.hall_name, c.cinema_location,
                IF(s.show_id IS NULL, NULL, TIMESTAMP(s.show_date, s.show_time)) AS starts_at
         FROM booking_details b
         LEFT JOIN shows s ON s.show_id = b.show_id
         LEFT JOIN cinema c ON c.cin_id = s.cin_id
         WHERE b.booking_ref = ?",
        's',
        [strtoupper(trim((string) $ref))]
    );
    if (!$booking) {
        return null;
    }
    $booking['seats'] = db_all(
        'SELECT seat_label, seat_class, price FROM booking_seats WHERE booking_id = ? ORDER BY LEFT(seat_label, 1), CAST(SUBSTRING(seat_label, 2) AS UNSIGNED)',
        'i',
        [(int) $booking['booking_id']]
    );
    $booking['snacks'] = db_all(
        'SELECT bs.qty, bs.price, sn.name FROM booking_snacks bs JOIN snacks sn ON sn.snack_id = bs.snack_id WHERE bs.booking_id = ?',
        'i',
        [(int) $booking['booking_id']]
    );
    return $booking;
}

function owns_booking($booking)
{
    $user_id = current_user_id();
    if (!$booking || !$user_id) {
        return false;
    }
    if ($booking['user_id'] !== null) {
        return (int) $booking['user_id'] === $user_id;
    }
    return isset($_SESSION['ux_email']) && strcasecmp($booking['user_email'], $_SESSION['ux_email']) === 0;
}

function booking_when($booking)
{
    if ($booking['show_date']) {
        return [show_date_label($booking['show_date']), show_time_label($booking['show_time'])];
    }
    return [$booking['user_date'], $booking['user_movie_dur']];
}

function refund_quote($booking)
{
    if ($booking['status'] !== 'Booked' || !$booking['starts_at']) {
        return null;
    }
    $seconds = strtotime($booking['starts_at']) - time();
    if ($seconds <= 0) {
        return null;
    }
    $percent = $seconds >= 86400 ? 100 : 50;
    return ['percent' => $percent, 'amount' => (int) floor((int) $booking['total_amount'] * $percent / 100)];
}

function cancel_booking($booking)
{
    $quote = refund_quote($booking);
    if (!$quote) {
        return ['ok' => false, 'message' => 'This booking can no longer be cancelled.'];
    }
    $db = db();
    mysqli_begin_transaction($db);
    $updated = db_query(
        "UPDATE booking_details SET status = 'Cancelled', refund_status = 'Pending', refund_amount = ?, cancelled_at = NOW()
         WHERE booking_id = ? AND status = 'Booked'",
        'ii',
        [$quote['amount'], (int) $booking['booking_id']]
    );
    if ($updated !== 1) {
        mysqli_rollback($db);
        return ['ok' => false, 'message' => 'This booking can no longer be cancelled.'];
    }
    db_query('DELETE FROM booking_seats WHERE booking_id = ?', 'i', [(int) $booking['booking_id']]);
    if ($booking['user_id'] !== null) {
        db_query(
            'UPDATE user_reg_login SET loyalty_points = GREATEST(loyalty_points - ?, 0) WHERE id = ?',
            'ii',
            [loyalty_points_for($booking['total_amount']), (int) $booking['user_id']]
        );
    }
    mysqli_commit($db);
    return ['ok' => true] + $quote;
}

function status_badge($status)
{
    $map = ['Booked' => 'green', 'Used' => 'blue', 'Cancelled' => 'red', 'Pending' => 'orange', 'Refunded' => 'green', 'Rejected' => 'red'];
    return '<span class="cx-badge cx-badge-' . ($map[$status] ?? 'grey') . '">' . e($status) . '</span>';
}
