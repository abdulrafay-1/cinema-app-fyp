<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';

$indus_delete_id=isset($_GET['ind']) ? $_GET['ind'] : '';

// a foreign key stops the delete when the row is still in use, and PHP 8 throws an exception for it
try {
    $deleted=db_query("delete from industry where indus_id=?", 'i', [$indus_delete_id]);
} catch (mysqli_sql_exception $ex) {
    echo "
    <script>
    alert('This industry is still used by movies. Delete those first.');
    window.location.assign('industry.php');
    </script>
    ";
    exit;
}

if($deleted>0)
{
    log_activity('Deleted industry', 'indus_id=' . $indus_delete_id);
    echo 
    "
    <script>
    window.location.assign('industry.php');
    </script>
    ";
}
else{
    echo "
    <script>
    alert('Something Went Wrong Try Another Time!');
    window.location.assign('industry.php');
    </script>
    ";
}
?>
