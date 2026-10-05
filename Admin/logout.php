<?php 
// session for admin

// the session has to be started before it can be destroyed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_unset();
session_destroy();

header('Location: login.php');
exit;
// session for admin

?>
