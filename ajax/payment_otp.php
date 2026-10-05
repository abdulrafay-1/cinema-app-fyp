<?php
require_once __DIR__ . '/../includes/functions.php';

if (!current_user_id()) {
    json_out(['ok' => false, 'message' => 'Your session has ended. Please sign in again.'], 401);
}

$mobile = preg_replace('/\D/', '', isset($_POST['mobile']) ? $_POST['mobile'] : '');
if (!preg_match('/^03[0-9]{9}$/', $mobile)) {
    json_out(['ok' => false, 'message' => 'Enter a valid mobile number like 03001234567.']);
}

$otp = (string) random_int(100000, 999999);
$_SESSION['payment_otp'] = ['code' => $otp, 'mobile' => $mobile, 'expires' => time() + 300];

json_out([
    'ok' => true,
    'otp' => $otp,
    'message' => 'OTP sent to ' . substr($mobile, 0, 4) . '*****' . substr($mobile, -2) . '. (Demo SMS: your code is ' . $otp . ')',
]);
