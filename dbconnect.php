<?php
$server = "localhost";
$database = "pikkit";
$user = "root";
$password = "";
 
$conn = new mysqli($server, $user, $password, $database);

if($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}
?>