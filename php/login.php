<?php
session_start();
require 'db.php';

$user = $_POST['user'];
$pass = $_POST['password'];

$sql = "SELECT * FROM usuarios WHERE usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $data = $result->fetch_assoc();

    if ($pass === $data['contrasena']) {
        $_SESSION['usuario'] = $data['usuario'];
        header("Location: ../homepage.html");
        exit();
    }
}

header("Location: ../login.html?error=1");
exit();
?>
