<?php
require_once __DIR__ . '/../includes/booking.php';

$show_id = isset($_GET['show_id']) ? (int) $_GET['show_id'] : 0;
if (!get_show($show_id)) {
    json_out(['ok' => false, 'message' => 'Show not found.'], 404);
}

json_out(['ok' => true] + seat_states($show_id, (int) current_user_id()));
