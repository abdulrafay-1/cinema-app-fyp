<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';

$cin_delete_id=isset($_GET['Cin']) ? $_GET['Cin'] : '';

// a foreign key stops the delete when the row is still in use, and PHP 8 throws an exception for it
try {
    $deleted=db_query("delete from cinema where cin_id=?", 'i', [$cin_delete_id]);
} catch (mysqli_sql_exception $ex) {
    echo "
    <script>
    alert('This cinema is still used by movies or shows. Delete those first.');
    window.location.assign('cinema.php');
    </script>
    ";
    exit;
}

if($deleted>0)
{
    log_activity('Deleted cinema', 'cin_id=' . $cin_delete_id);
    echo 
    "
    <script>
    window.location.assign('cinema.php');
    </script>
    ";
}
else{
    echo "
    <script>
    alert('Something Went Wrong Try Another Time!');
    window.location.assign('cinema.php');
    </script>
    ";
}
?>
