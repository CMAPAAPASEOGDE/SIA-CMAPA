<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['idUsuario'])) {
    echo json_encode(['exito' => false, 'mensaje' => 'No estás autenticado.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$actual = $data['actual'] ?? '';
$nueva = $data['nueva'] ?? '';

if (!$actual || !$nueva) {
    echo json_encode(['exito' => false, 'mensaje' => 'Datos incompletos.']);
    exit();
}

// Conexión a base de datos
require 'conexion.php';

// Obtener datos actuales
$idUsuario = $_SESSION['idUsuario'];
$stmt = $conn->prepare("SELECT contrasena FROM usuarios WHERE idUsuario = :id");
$stmt->bindParam(':id', $idUsuario, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['contrasena'] !== $actual) {
    echo json_encode(['exito' => false, 'mensaje' => 'Contraseña actual incorrecta.']);
    exit();
}

// Actualizar contraseña
$update = $conn->prepare("UPDATE usuarios SET contrasena = :nueva WHERE idUsuario = :id");
$update->bindParam(':nueva', $nueva, PDO::PARAM_STR);
$update->bindParam(':id', $idUsuario, PDO::PARAM_INT);

if ($update->execute()) {
    echo json_encode(['exito' => true, 'mensaje' => 'La contraseña se ha cambiado correctamente. Los cambios se aplicarán la próxima vez que inicies sesión.']);
} else {
    echo json_encode(['exito' => false, 'mensaje' => 'Error al actualizar la contraseña.']);
}
?>
