<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';

$date_delete_id=isset($_GET['dat']) ? $_GET['dat'] : '';

// a foreign key stops the delete when the row is still in use, and PHP 8 throws an exception for it
try {
    $deleted=db_query("delete from date where date_id=?", 'i', [$date_delete_id]);
} catch (mysqli_sql_exception $ex) {
    echo "
    <script>
    alert('This date is still used by movies. Delete those first.');
    window.location.assign('date.php');
    </script>
    ";
    exit;
}

if($deleted>0)
{
    log_activity('Deleted date', 'date_id=' . $date_delete_id);
    echo 
    "
    <script>
    window.location.assign('date.php');
    </script>
    ";
}
else{
    echo "
    <script>
    alert('Something Went Wrong Try Another Time!');
    window.location.assign('date.php');
    </script>
    ";
}
?>
