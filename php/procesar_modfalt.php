<?php
session_start();
header('Content-Type: application/json');

// 1) Sesión y rol válidos (usuarios 1=admin o 2=operativo pueden generar la solicitud)
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']); exit;
}
$idRolSesion = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRolSesion, [1, 2], true)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']); exit;
}

// 2) Datos recibidos del formulario
$idCodigo    = (int)($_POST['idCodigo'] ?? 0);
$descripcion = trim($_POST['descripcion'] ?? '');
$cantidad    = (int)($_POST['cantidad'] ?? 0);

if ($idCodigo <= 0 || $cantidad <= 0 || $descripcion === '') {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos']); exit;
}

// 3) Conexión SQL Server
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
    echo json_encode(['success' => false, 'message' => 'Error de conexión', 'detail' => print_r(sqlsrv_errors(), true)]); 
    exit;
}

/*
  4) Registrar la solicitud en Modificaciones
     - idRol: 1 (destinatario/cola de administrador)
     - descripcion: motivo que escribió el usuario
     - fecha: SYSDATETIME()
     - solicitudRevisada: 0 (pendiente)
     - cantidad / idCodigo: según formulario
     - tipo: 'alta'
*/
$destinatarioAdmin = 1;
$tipo = 'alta';

$sql = "INSERT INTO Modificaciones (idRol, descripcion, fecha, solicitudRevisada, cantidad, idCodigo, tipo)
        VALUES (?, ?, SYSDATETIME(), 0, ?, ?, ?)";
$params = [$destinatarioAdmin, $descripcion, $cantidad, $idCodigo, $tipo];

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'No se pudo registrar la solicitud', 'detail' => print_r(sqlsrv_errors(), true)]);
    exit;
}
sqlsrv_free_stmt($stmt);

// (Opcional) devolver el ID recién creado por si quieres mostrarlo/llevarlo a otra pantalla
$idNew = null;
$stmtId = sqlsrv_query($conn, "SELECT CAST(SCOPE_IDENTITY() AS INT) AS idModificacion");
if ($stmtId) {
    if ($row = sqlsrv_fetch_array($stmtId, SQLSRV_FETCH_ASSOC)) {
        $idNew = (int)$row['idModificacion'];
    }
    sqlsrv_free_stmt($stmtId);
}

sqlsrv_close($conn);
echo json_encode(['success' => true, 'idModificacion' => $idNew]); 
exit;
