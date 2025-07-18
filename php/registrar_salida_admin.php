<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit();
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
if ($conn === false) die(print_r(sqlsrv_errors(), true));

// Recibir datos
$idCodigo = (int)($_POST['idCodigo'] ?? 0);
$cantidad = (int)($_POST['cantidad'] ?? 0);
$fecha = $_POST['fecha'] ?? '';
$comentarios = "MOVIMIENTO DE ADMINISTRADOR";

// Validación
if ($idCodigo === 0 || $cantidad <= 0 || empty($fecha)) {
    header("Location: ../admnedtext.php");
    exit();
}

// Verificar stock
$sqlStock = "SELECT cantidadActual FROM Inventario WHERE idCodigo = ?";
$stmtStock = sqlsrv_query($conn, $sqlStock, [$idCodigo]);
$rowStock = sqlsrv_fetch_array($stmtStock, SQLSRV_FETCH_ASSOC);
if (!$rowStock || ($rowStock['cantidadActual'] - $cantidad) < 0) {
    header("Location: ../admnedtexter2.php");
    exit();
}

// Insertar salida
$sqlInsert = "INSERT INTO SalidaSinorden (areaSolicitante, encargadoArea, fecha, comentarios, idCodigo, cantidad)
              VALUES (?, ?, ?, ?, ?, ?)";
$paramsInsert = ['ADMIN', 'ADMIN', $fecha, $comentarios, $idCodigo, $cantidad];
$stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);
if ($stmtInsert === false) die(print_r(sqlsrv_errors(), true));

// Actualizar inventario
$nuevaCantidad = $rowStock['cantidadActual'] - $cantidad;
$sqlUpdate = "UPDATE Inventario SET cantidadActual = ?, ultimaActualizacion = ? WHERE idCodigo = ?";
$paramsUpdate = [$nuevaCantidad, $fecha, $idCodigo];
$stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);
if ($stmtUpdate === false) die(print_r(sqlsrv_errors(), true));

header("Location: ../admnedtextcf.php");
exit();
?>
