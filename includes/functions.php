<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Karachi');
mysqli_set_charset($con, 'utf8mb4');
mysqli_query($con, "SET time_zone = '+05:00'");
$GLOBALS['app_db'] = $con;

function db()
{
    return $GLOBALS['app_db'];
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function db_query($sql, $types = '', $params = [])
{
    $stmt = mysqli_prepare(db(), $sql);
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
        $html .= '<div class="alert alert-' . e($item['type']) . '" role="alert">'
            . e($item['message'])
            . '</div>';
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

function require_login()
{
    if (!current_user_id()) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'];
        redirect('signin.php');
    }
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_ok()
{
    return isset($_POST['csrf']) && hash_equals(csrf_token(), (string) $_POST['csrf']);
}

function json_out($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
