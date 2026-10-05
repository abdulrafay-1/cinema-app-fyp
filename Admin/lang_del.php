<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';

$lang_delete_id=isset($_GET['lang']) ? $_GET['lang'] : '';

// a foreign key stops the delete when the row is still in use, and PHP 8 throws an exception for it
try {
    $deleted=db_query("delete from language where lang_id=?", 'i', [$lang_delete_id]);
} catch (mysqli_sql_exception $ex) {
    echo "
    <script>
    alert('This language is still used by movies. Delete those first.');
    window.location.assign('language.php');
    </script>
    ";
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
    echo "
    <script>
    alert('Something Went Wrong Try Another Time!');
    window.location.assign('language.php');
    </script>
    ";
}
?>
