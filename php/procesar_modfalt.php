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
    echo json_encode(['success' => false, 'message' => 'Error de conexión', 'detail' => print_r(sqlsrv_errors(), true)]); exit;
}

/**
 * Estas solicitudes de ALTA están dirigidas a ADMIN,
 * por lo tanto se guardan con idRol = 1 (destinatario).
 */
$destinoRol = 1;

$sql = "INSERT INTO Notificaciones (idRol, descripcion, fecha, solicitudRevisada, cantidad, idCodigo)
        VALUES (?, ?, SYSDATETIME(), 0, ?, ?)";
$params = [$destinoRol, $descripcion, $cantidad, $idCodigo]; // <-- SOLO 4 parámetros y en el orden correcto

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'No se pudo registrar la solicitud', 'detail' => print_r(sqlsrv_errors(), true)]); 
    exit;
}

echo json_encode(['success' => true]); exit;
