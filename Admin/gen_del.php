<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';

$genre_delete_id=isset($_GET['gen']) ? $_GET['gen'] : '';

// a foreign key stops the delete when the row is still in use, and PHP 8 throws an exception for it
try {
    $deleted=db_query("delete from genre where gen_id=?", 'i', [$genre_delete_id]);
} catch (mysqli_sql_exception $ex) {
    echo "
    <script>
    alert('This genre is still used by movies. Delete those first.');
    window.location.assign('genre.php');
    </script>
    ";
    exit;
}

if($deleted>0)
{
    log_activity('Deleted genre', 'gen_id=' . $genre_delete_id);
    echo 
    "
    <script>
    window.location.assign('genre.php');
    </script>
    ";
}
else{
    echo "
    <script>
    alert('Something Went Wrong Try Another Time!');
    window.location.assign('genre.php');
    </script>
    ";
}
?>
