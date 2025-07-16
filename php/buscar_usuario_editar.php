<?php
session_start();
require_once 'conexion.php'; // Asegúrate que este archivo conecta correctamente a tu DB

$usuario = $_POST['usuario'] ?? '';

if (empty($usuario)) {
    header("Location: ../admnusredsrcher.php"); // página de "usuario no encontrado"
    exit();
}

// Buscar el usuario en la base de datos
$stmt = $conn->prepare("SELECT usuario, nombre, idRol, estatus FROM usuarios WHERE usuario = ?");
$stmt->execute([$usuario]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $_SESSION['editar_usuario'] = $user;
    header("Location: ../admnusredt.php");
    exit();
} else {
    header("Location: ../admnusredsrcher.php");
    exit();
}
