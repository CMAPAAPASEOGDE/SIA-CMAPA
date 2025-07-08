<?php
session_start();
require 'conexion.php';

// Obtener datos del formulario
$usuario = $_POST['user'];
$password = $_POST['password'];

echo "Usuario recibido: $usuario<br>";
echo "Contraseña recibida: $password<br>";

// Consulta segura con parámetros
$sql = "SELECT * FROM usuarios WHERE usuario = :usuario AND contrasena = :contrasena AND estatus = 1";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':usuario', $usuario);
$stmt->bindParam(':contrasena', $password); // Si usas hash más adelante, cámbialo
$stmt->execute();

// Verificar si existe
if ($stmt->rowCount() > 0) {
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['idUsuario'] = $userData['idUsuario'];
    $_SESSION['usuario'] = $userData['usuario'];
    $_SESSION['apodo'] = $userData['apodo'];
    $_SESSION['rol'] = $userData['idRol']; 

    echo "Login exitoso.<br>";
    echo "Redireccionando a homepage.php...";
    header("Location: ../homepage.php");
    exit();
} else {
    echo "Credenciales incorrectas. Redirigiendo...<br>";
    echo "Consulta realizada: $sql<br>";
    // Redirigir con error
    header("Location: ../index.php?error=1");
    exit();
}
?>
