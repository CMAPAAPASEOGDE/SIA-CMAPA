<?php
session_start();
require 'db.php';

// Sanitiza y recoge datos del formulario
$usuario = trim($_POST['user'] ?? '');
$clave   = trim($_POST['password'] ?? '');

if ($usuario === '' || $clave === '') {
    die("Faltan datos.");
}

// Consulta segura
$stmt = $conn->prepare(
    "SELECT * FROM usuarios
     WHERE idUsuario = :usuario AND contrasena = :contrasena"
);
$stmt->execute([
    ':usuario'    => $usuario,
    ':contrasena' => $clave  // ⚠ En producción usa password_hash y password_verify
]);

if ($stmt->rowCount() === 1) {
    $_SESSION['usuario'] = $usuario;
    header("Location: ../homepage.html");
    exit();
}

echo "<script>alert('Usuario o contraseña incorrectos');history.back();</script>";
?>
