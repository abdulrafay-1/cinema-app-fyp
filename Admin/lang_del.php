<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/admin_alert.php';

// the delete link carries the CSRF token, so another website can't trigger a delete
if (!hash_equals(csrf_token(), (string) ($_GET['csrf'] ?? ''))) {
    echo swal_page('error', 'Invalid request. Please reload the page and try again.', 'language.php');
    exit;
}

$lang_delete_id=isset($_GET['lang']) ? $_GET['lang'] : '';

// a foreign key stops the delete when the row is still in use, and PHP 8 throws an exception for it
try {
    $deleted=db_query("delete from language where lang_id=?", 'i', [$lang_delete_id]);
} catch (mysqli_sql_exception $ex) {
    echo swal_page('warning', 'This language is still used by movies. Delete those first.', 'language.php');
    exit;
}

if($deleted>0)
{
    log_activity('Deleted language', 'lang_id=' . $lang_delete_id);
    echo 
    "
    <script>
    window.location.assign('language.php');
    </script>
    ";
}
else{
    echo swal_page('error', 'Something Went Wrong Try Another Time!', 'language.php');
}
?>
