<?php
$host = '';
$user = '';
$pass = '';
$dbname = '';

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("DB 연결 실패: " . $conn->connect_error);
}
?>