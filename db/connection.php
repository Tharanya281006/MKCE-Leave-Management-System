<?php

// MKCE application timezone: India Standard Time.
date_default_timezone_set('Asia/Kolkata');


$host = "localhost";
$username = "root";
$password = "";
$database = "mkce_leave";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

?>