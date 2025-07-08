<?php
session_start();
require 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtener datos del formulario
    $usuario = $_POST['user'];
    $password = $_POST['password'];

    // Consulta segura
    $sql = "SELECT * FROM usuarios WHERE usuario = :usuario AND contrasena = :contrasena AND estatus = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->bindParam(':contrasena', $password, PDO::PARAM_STR);
    $stmt->execute();

    // Verificar si existe
    if ($stmt->rowCount() > 0) {
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['idUsuario'] = $userData['idUsuario'];
        $_SESSION['usuario'] = $userData['usuario'];
        $_SESSION['apodo'] = $userData['apodo'];
        $_SESSION['rol'] = $userData['idRol'];

        header("Location: /homepage.php");  // asegúrate que está en la raíz
        exit();
    } else {
        header("Location: /homepage.php");
        exit();
    }
} else {
    header("Location: /index.php");
    exit();
}
?>
