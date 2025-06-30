<?php
session_start();
require 'db.php';

// Sanitiza y recibe datos del formulario
$usuario = trim($_POST['user']      ?? '');
$clave   = trim($_POST['password']  ?? '');

if ($usuario === '' || $clave === '') {
    die("Faltan datos.");
}

// Consulta segura
$stmt = $conn->prepare(
    "SELECT * FROM usuarios
     WHERE usuario = :usuario AND contrasena = :contrasena"
);
$stmt->execute([
    ':usuario'    => $usuario,
    ':contrasena' => $clave,      // ⚠️  En producción usa password_hash
]);

if ($stmt->rowCount() === 1) {
    // Usuario válido: guarda datos en sesión (si los necesitas)
    $_SESSION['usuario'] = $usuario;

    // Redirige a la página principal
    header("Location: ../homepage.html");
    exit();
}

echo "<script>alert('Usuario o contraseña incorrectos');history.back();</script>";
?>
