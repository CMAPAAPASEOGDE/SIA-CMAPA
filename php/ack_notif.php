<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Sesión no válida']); exit;
}

$rol = (int)($_SESSION['rol'] ?? 0);
if ($rol !== 2) {
  echo json_encode(['success' => false, 'message' => 'Sin permisos']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  echo json_encode(['success' => false, 'message' => 'ID inválido']); exit;
}

$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
  "Database" => "db_sia",
  "Uid" => "cmapADMIN",
  "PWD" => "@siaADMN56*",
  "Encrypt" => true,
  "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
  echo json_encode(['success' => false, 'message' => 'Error de conexión']); exit;
}

// Elimina la notificación del buzón del usuario (resueltas)
$sql = "DELETE FROM Notificaciones WHERE idNotificacion = ? AND idRol = 2 AND solicitudRevisada = 1";
$stmt = sqlsrv_query($conn, $sql, [$id]);

echo json_encode(['success' => $stmt !== false]);
