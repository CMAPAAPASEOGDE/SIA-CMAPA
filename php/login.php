<?php
session_start();
require 'conexion.php';

// Obtener datos del formulario
$usuario = $_POST['user'];
$contrasena = $_POST['password'];

// Consulta segura con parámetros
$sql = "SELECT * FROM Usuarios WHERE usuario = :usuario AND contrasena = :contrasena AND estatus = 1";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':usuario', $usuario);
$stmt->bindParam(':contrasena', $contrasena); // si después usas hash, aquí cambia
$stmt->execute();

// Verificar si existe
if ($stmt->rowCount() > 0) {
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['idUsuario'] = $userData['idUsuario'];
    $_SESSION['usuario'] = $userData['usuario'];
    $_SESSION['apodo'] = $userData['apodo'];
    $_SESSION['rol'] = $userData['idRol'];

    header("Location: ../homepage.php");
    exit();
} else {
    // Redirigir con error
    header("Location: ../index.php?error=1");
    exit();
}
?>

