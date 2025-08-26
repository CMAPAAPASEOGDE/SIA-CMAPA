<?php
session_start();
header('Content-Type: application/json');

// Sesión válida
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Sesión no válida']); exit;
}

// Este formulario lo usan roles 1 y 2
$idRolSesion = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRolSesion, [1, 2], true)) {
  echo json_encode(['success' => false, 'message' => 'Sin permisos']); exit;
}

// Datos
$idCodigo    = (int)($_POST['idCodigo'] ?? 0);
$descripcion = trim($_POST['descripcion'] ?? '');

if ($idCodigo <= 0 || $descripcion === '') {
  echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos']); exit;
}

// Conexión
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
  echo json_encode(['success' => false, 'message' => 'Error de conexión', 'detail' => print_r(sqlsrv_errors(), true)]); exit;
}

/*
 * Nueva solicitud de EDICIÓN DE DETALLES:
 *  - Se registra en Modificaciones para el ADMIN (idRol=1)
 *  - solicitudRevisada=0 (pendiente)
 *  - tipo='detalle'
 *  - cantidad=0 (no aplica ajuste de inventario)
 *  - fecha=SYSDATETIME()
 */
$sql = "INSERT INTO Modificaciones
        (idRol, descripcion, fecha, solicitudRevisada, cantidad, idCodigo, tipo)
        VALUES (1, ?, SYSDATETIME(), 0, 0, ?, 'detalle')";
$params = [$descripcion, $idCodigo];

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
  echo json_encode([
    'success' => false,
    'message' => 'No se pudo registrar la solicitud',
    'detail'  => print_r(sqlsrv_errors(), true)
  ]);
  exit;
}

echo json_encode(['success' => true]);
