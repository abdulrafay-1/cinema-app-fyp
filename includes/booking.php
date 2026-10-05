<?php
require_once __DIR__ . '/functions.php';

const HOLD_MINUTES = 5;
const MAX_SEATS = 10;
const SEAT_CLASSES = ['Economy', 'Business', 'First'];

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
        "SELECT s.*, m.movie_name, m.movie_img, m.description, c.cinema_name, c.cinema_location,
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
    return '<link rel="stylesheet" href="' . $base . 'assets/booking.css?v=1">';
}
