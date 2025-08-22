<?php
session_start();
header('Content-Type: application/json');

// Solo usuarios autenticados y de rol 2 o 3 pueden confirmar lectura
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Sesión no válida']); exit;
}
$rol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($rol, [2,3], true)) {
  echo json_encode(['success' => false, 'message' => 'Sin permisos']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  echo json_encode(['success' => false, 'message' => 'ID inválido']); exit;
}

$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
  "Database" => "db_sia",
  "Uid"      => "cmapADMIN",
  "PWD"      => "@siaADMN56*",
  "Encrypt"  => true,
  "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
  echo json_encode(['success' => false, 'message' => 'Error de conexión']); exit;
}

// Marcar como leída (estatusRevision=1)
$sql  = "UPDATE Notificaciones SET estatusRevision = 1 WHERE idNotificacion = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);

echo json_encode(['success' => $stmt !== false]);
