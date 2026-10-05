<?php
require_once __DIR__ . '/../includes/booking.php';

if (!current_user_id()) {
    json_out(['ok' => false, 'message' => 'Your session has ended. Please sign in again.'], 401);
}

list($promo, $error) = find_promo(isset($_POST['code']) ? $_POST['code'] : '');
if (!$promo) {
    json_out(['ok' => false, 'message' => $error]);
}

json_out([
    'ok' => true,
    'code' => $promo['code'],
    'percent' => (int) $promo['discount_percent'],
    'message' => $promo['code'] . ' applied: ' . (int) $promo['discount_percent'] . '% off your order.',
]);
