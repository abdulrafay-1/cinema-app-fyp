<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';

$price_delete_id=isset($_GET['pr']) ? $_GET['pr'] : '';

// a foreign key stops the delete when the row is still in use, and PHP 8 throws an exception for it
try {
    $deleted=db_query("delete from movie_price where price_id=?", 'i', [$price_delete_id]);
} catch (mysqli_sql_exception $ex) {
    echo "
    <script>
    alert('This price row could not be deleted. Try again.');
    window.location.assign('price.php');
    </script>
    ";
    exit;
}

if($deleted>0)
{
    log_activity('Deleted price row', 'price_id=' . $price_delete_id);
    echo 
    "
    <script>
    window.location.assign('price.php');
    </script>
    ";
}
else{
    echo "
    <script>
    alert('Something Went Wrong Try Another Time!');
    window.location.assign('price.php');
    </script>
    ";
}
?>
