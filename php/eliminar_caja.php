<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || (int)$_SESSION['rol'] !== 1) {
    header("Location: index.php");
    exit();
}

$idCaja = isset($_GET['idCaja']) ? intval($_GET['idCaja']) : 0;
if ($idCaja <= 0) {
    header("Location: boxes.php");
    exit();
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
    die(print_r(sqlsrv_errors(), true));
}

// Eliminar contenido primero
sqlsrv_query($conn, "DELETE FROM CajaContenido WHERE idCaja = ?", [$idCaja]);
// Luego eliminar el registro
$sql = "DELETE FROM CajaRegistro WHERE idCaja = ?";
$stmt = sqlsrv_query($conn, $sql, [$idCaja]);

if ($stmt) {
    header("Location: boxes.php?deleted=1");
    exit();
} else {
    die(print_r(sqlsrv_errors(), true));
}
