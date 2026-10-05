<?php
require_once __DIR__ . '/includes/booking.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    flash('danger', 'Invalid request. Please try again.');
    redirect('userbooking.php');
}

$booking = load_booking($_POST['ref'] ?? '');
if (!$booking || !owns_booking($booking)) {
    flash('danger', 'Booking not found.');
    redirect('userbooking.php');
}

$result = cancel_booking($booking);
if (!$result['ok']) {
    flash('danger', $result['message']);
    redirect('userbooking.php');
}

flash('success', 'Booking ' . $booking['booking_ref'] . ' cancelled. A refund of ' . money($result['amount']) . ' (' . $result['percent'] . '%) is pending approval.');
redirect('userbooking.php?tab=cancelled');
