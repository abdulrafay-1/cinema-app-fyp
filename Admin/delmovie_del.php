<?php
include('auth.php');
require_once __DIR__ . '/../includes/admin_functions.php';

$movie_delete_id=isset($_GET['delmov']) ? $_GET['delmov'] : '';

// a foreign key stops the delete when the row is still in use, and PHP 8 throws an exception for it
try {
    $deleted=db_query("delete from addmovie where movie_id=?", 'i', [$movie_delete_id]);
} catch (mysqli_sql_exception $ex) {
    echo "
    <script>
    alert('This movie is still used by shows. Delete those first.');
    window.location.assign('viewmovie.php');
    </script>
    ";
    exit;
}

if($deleted>0)
{
    log_activity('Deleted movie', 'movie_id=' . $movie_delete_id);
    echo 
    "
    <script>
    window.location.assign('viewmovie.php');
    </script>
    ";
}
else{
    echo "
    <script>
    alert('Something Went Wrong Try Another Time!');
    window.location.assign('viewmovie.php');
    </script>
    ";
}
?>
