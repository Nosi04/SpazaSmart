<?php

$servername = "sql208.infinityfree.com";
$username = "if0_43485218";
$password = "nosipho2605";
$dbname = "if0_43485218_spazasmart";

$conn = new mysqli(
    $servername,
    $username,
    $password,
    $dbname
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>