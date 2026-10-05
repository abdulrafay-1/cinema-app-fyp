<?php
require_once __DIR__ . '/functions.php';

function log_activity($action, $details = '')
{
    $admin = isset($_SESSION['admin']) ? $_SESSION['admin'] : null;
    return db_query(
        'INSERT INTO activity_log (admin_email, action, details) VALUES (?, ?, ?)',
        'sss',
        [$admin, $action, $details]
    );
}
