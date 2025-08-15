<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Sesión no válida']); exit;
}

// Solo usuarios (rol 2) deben poder “quitar” su notificación
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

/*
  Estrategia simple: eliminar la notificación del “buzón” de usuarios
  (idRol=2 y ya resuelta solicitudRevisada=1).
*/
$sql = "DELETE FROM Notificaciones WHERE idNotificacion = ? AND idRol = 2 AND solicitudRevisada = 1";
$stmt = sqlsrv_query($conn, $sql, [$id]);

if ($stmt === false) {
  echo json_encode(['success' => false, 'message' => 'No se pudo actualizar']); exit;
}

echo json_encode(['success' => true]);
