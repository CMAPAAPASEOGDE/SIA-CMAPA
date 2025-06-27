<?php
$host = "localhost";
$db = "dbSiacmapa";
$user = "root";
$pass = ""; // si tu XAMPP no tiene contraseña, déjalo vacío

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
?>
