<?php
require_once __DIR__ . '/../includes/booking.php';

$user_id = current_user_id();
if (!$user_id) {
    json_out(['ok' => false, 'message' => 'Your session has ended. Please sign in again.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$action = isset($_POST['action']) ? $_POST['action'] : 'hold';
if ($action === 'release') {
    release_user_holds($user_id);
    json_out(['ok' => true]);
}

$show = bookable_show(isset($_POST['show_id']) ? $_POST['show_id'] : 0);
if (!$show) {
    json_out(['ok' => false, 'message' => 'This show is no longer available for booking.'], 404);
}
if (!movie_prices($show['movie_name'])) {
    json_out(['ok' => false, 'message' => 'Ticket prices are not set for this movie yet.'], 422);
}

$raw = isset($_POST['seats']) ? strtoupper((string) $_POST['seats']) : '';
$labels = array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)))));
if (!$labels) {
    json_out(['ok' => false, 'message' => 'Please select at least one seat.'], 422);
}
if (count($labels) > MAX_SEATS) {
    json_out(['ok' => false, 'message' => 'You can book up to ' . MAX_SEATS . ' seats at a time.'], 422);
}
foreach ($labels as $label) {
    if (!valid_seat_label($show, $label)) {
        json_out(['ok' => false, 'message' => 'Seat ' . $label . ' does not exist in this hall.'], 422);
    }
}

$conflicts = hold_seats($show['show_id'], $user_id, $labels);
if ($conflicts) {
    json_out([
        'ok' => false,
        'conflicts' => $conflicts,
        'message' => 'Sorry, ' . implode(', ', $conflicts) . (count($conflicts) > 1 ? ' were' : ' was') . ' just taken by someone else. Please choose other seats.',
    ], 409);
}

json_out([
    'ok' => true,
    'expires_in' => HOLD_MINUTES * 60,
    'redirect' => 'checkout.php?show_id=' . (int) $show['show_id'],
]);
