<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin'])) {
    if (!headers_sent()) {
        header('Location: login.php');
    } else {
        echo "<script>window.location.assign('login.php')</script>";
    }
    exit;
}
