<?php
// Use the credentials provided by Railway (MySQL plugin)
$host = "mysql.railway.internal";  // <-- Railway host
$port = 3306;                                 // <-- Railway port
$user = "root";                               // <-- Railway user
$pass = "UyIfPlnMFcROjQPDwqUhOigqXbrfhZjV";              // <-- Railway password
$db   = "railway";                            // <-- Railway database name

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
