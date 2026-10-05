<?php 

$con=mysqli_connect(getenv("DB_HOST") ?: "localhost","root","","dbmo");

if(!$con){

      echo "Database Connection Failed";

};


?>