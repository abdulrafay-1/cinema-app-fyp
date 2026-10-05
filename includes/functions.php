<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Karachi');
mysqli_set_charset($con, 'utf8mb4');
mysqli_query($con, "SET time_zone = '+05:00'");

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function db_query($sql, $types = '', $params = [])
{
    global $con;
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        return false;
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    $result = mysqli_stmt_get_result($stmt);
    if ($result === false) {
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $affected;
    }
    mysqli_stmt_close($stmt);
    return $result;
}

function db_all($sql, $types = '', $params = [])
{
    $result = db_query($sql, $types, $params);
    return $result instanceof mysqli_result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function db_one($sql, $types = '', $params = [])
{
    $result = db_query($sql, $types, $params);
    return $result instanceof mysqli_result ? mysqli_fetch_assoc($result) : null;
}

function current_user_id()
{
    if (!empty($_SESSION['ux_id'])) {
        return (int) $_SESSION['ux_id'];
    }
    if (!empty($_SESSION['ux_email'])) {
        $user = db_one('SELECT id FROM user_reg_login WHERE email = ? LIMIT 1', 's', [$_SESSION['ux_email']]);
        if ($user) {
            $_SESSION['ux_id'] = (int) $user['id'];
            return $_SESSION['ux_id'];
        }
    }
    return null;
}

function generate_booking_ref($booking_id, $year = null)
{
    return 'CNX-' . ($year ?: date('Y')) . '-' . str_pad((string) $booking_id, 5, '0', STR_PAD_LEFT);
}

function money($amount)
{
    return 'PKR ' . number_format((int) $amount);
}

function flash($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flash()
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['flash'] as $item) {
        $html .= '<div class="alert alert-' . e($item['type']) . ' alert-dismissible fade show" role="alert">'
            . e($item['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" data-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

function redirect($url)
{
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo '<script>window.location.assign(' . json_encode($url) . ')</script>';
    }
    exit;
}
