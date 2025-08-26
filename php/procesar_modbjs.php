<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']); exit;
}

// Roles permitidos que pueden solicitar bajas
$idRolSesion = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRolSesion, [1,2,3], true)) {
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

/*
 * Registrar BAJA en Modificaciones:
 * - idRol = 1 (destinatario admin)
 * - tipo = 'baja'
 * - fecha = SYSDATETIME()
 * - solicitudRevisada = 0 (pendiente)
 */
$sql = "INSERT INTO Modificaciones
        (idRol, descripcion, fecha, solicitudRevisada, cantidad, idCodigo, tipo)
        VALUES (1, ?, SYSDATETIME(), 0, ?, ?, 'baja')";
$params = [$descripcion, $cantidad, $idCodigo];

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
