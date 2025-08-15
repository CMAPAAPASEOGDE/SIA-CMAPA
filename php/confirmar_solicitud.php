<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Sesión no válida']); exit;
}
if ((int)($_SESSION['rol'] ?? 0) !== 1) {
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

sqlsrv_begin_transaction($conn);

try {
  // 1) Traer la notificación original (pendiente para admin)
  $sel = sqlsrv_query(
    $conn,
    "SELECT descripcion, cantidad, idCodigo, tipo
     FROM Notificaciones
     WHERE idNotificacion = ? AND idRol = 1 AND solicitudRevisada = 0",
    [$id]
  );
  if ($sel === false || !($row = sqlsrv_fetch_array($sel, SQLSRV_FETCH_ASSOC))) {
    throw new Exception('No encontrada o ya atendida.');
  }

  // 2) Marcar como atendida la de admin
  $upd = sqlsrv_query(
    $conn,
    "UPDATE Notificaciones SET solicitudRevisada = 1 WHERE idNotificacion = ? AND idRol = 1",
    [$id]
  );
  if ($upd === false) throw new Exception('No se pudo actualizar admin.');

  // 3) Crear la notificación para el usuario (pendiente para él)
  $ins = sqlsrv_query(
    $conn,
    "INSERT INTO Notificaciones (idRol, descripcion, fecha, solicitudRevisada, cantidad, idCodigo, tipo)
     VALUES (2, ?, SYSDATETIME(), 0, ?, ?, ?)",
    [$row['descripcion'], (int)($row['cantidad'] ?? 0), (int)($row['idCodigo'] ?? 0), ($row['tipo'] ?? 'detalle')]
  );
  if ($ins === false) throw new Exception('No se pudo crear para usuario.');

  sqlsrv_commit($conn);
  echo json_encode(['success' => true]);
} catch (Exception $e) {
  sqlsrv_rollback($conn);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
