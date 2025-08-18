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
if ($conn === false) { echo json_encode(['success' => false, 'message' => 'Error de conexión']); exit; }

sqlsrv_begin_transaction($conn);
try {
  // 1) Traer la notificación PENDIENTE de admin
  $sel = sqlsrv_query($conn,
    "SELECT descripcion, cantidad, idCodigo, tipo
     FROM Notificaciones
     WHERE idNotificacion = ? AND idRol = 1 AND solicitudRevisada = 0",
    [$id]
  );
  if ($sel === false || !($row = sqlsrv_fetch_array($sel, SQLSRV_FETCH_ASSOC))) {
    throw new Exception('Solicitud no encontrada o ya atendida.');
  }

  // 2) Marcar la de admin como revisada
  $upd = sqlsrv_query($conn,
    "UPDATE Notificaciones
     SET solicitudRevisada = 1
     WHERE idNotificacion = ? AND idRol = 1",
    [$id]
  );
  if ($upd === false) throw new Exception('No se pudo actualizar el estatus de admin.');

  // 3) Crear la notificación para el USUARIO:
  //    - idRol = 2
  //    - solicitudRevisada = 1 (significa "resuelta por admin")
  //    - confirmacionLectura = 0 (pendiente de que el usuario la marque como leída)
  $ins = sqlsrv_query($conn,
    "INSERT INTO Notificaciones
     (idRol, descripcion, fecha, solicitudRevisada, cantidad, idCodigo, tipo, confirmacionLectura)
     VALUES (2, ?, SYSDATETIME(), 1, ?, ?, ?, 0)",
    [$row['descripcion'], (int)$row['cantidad'], (int)$row['idCodigo'], (string)$row['tipo']]
  );
  if ($ins === false) throw new Exception('No se pudo crear la notificación para el usuario.');

  sqlsrv_commit($conn);
  echo json_encode(['success' => true]);
} catch (Exception $e) {
  sqlsrv_rollback($conn);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
