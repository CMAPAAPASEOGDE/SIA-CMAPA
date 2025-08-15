<?php
session_start();
header('Content-Type: application/json');

// Autenticación
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']); exit;
}
$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1,2], true)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']); exit;
}

// Entrada
$idCodigo    = (int)($_POST['idCodigo'] ?? 0);
$descripcion = trim($_POST['descripcion'] ?? '');

if ($idCodigo <= 0 || $descripcion === '') {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos']); exit;
}

// Conexión SQL Server
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

// Insert en Notificaciones (destino: admins idRol = 1; tipo = 'detalle'; cantidad = 0)
$sql = "INSERT INTO Notificaciones (idRol, descripcion, fecha, solicitudRevisada, cantidad, idCodigo, tipo)
        VALUES (?, ?, SYSDATETIME(), 0, ?, ?, ?)";
$params = [1, $descripcion, 0, $idCodigo, 'detalle'];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'No se pudo registrar la solicitud']);
    exit;
}

echo json_encode(['success' => true]);
