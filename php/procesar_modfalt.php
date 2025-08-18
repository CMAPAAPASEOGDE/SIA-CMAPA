<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']); exit;
}
$idRolSesion = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRolSesion, [1,2], true)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']); exit;
}

$idCodigo    = (int)($_POST['idCodigo'] ?? 0);
$descripcion = trim($_POST['descripcion'] ?? '');
$cantidad    = (int)($_POST['cantidad'] ?? 0);

if ($idCodigo <= 0 || $descripcion === '' || $cantidad <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos']); exit;
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

/**
 * Se crea NOTIFICACIÓN para ADMIN (idRol=1):
 * - solicitudRevisada = 0 (pendiente para admin)
 * - confirmacionLectura = 0 (no aplica realmente a admin, pero queda en 0 por consistencia)
 * - tipo = 'alta'
 */
$sql = "INSERT INTO Notificaciones
        (idRol, descripcion, fecha, solicitudRevisada, cantidad, idCodigo, tipo, confirmacionLectura)
        VALUES (?, ?, SYSDATETIME(), 0, ?, ?, ?, 0)";
$params = [1, $descripcion, $cantidad, $idCodigo, 'alta'];

$stmt = sqlsrv_query($conn, $sql, $params);
echo json_encode(['success' => $stmt !== false]);
