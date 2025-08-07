<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$serverName = "sqlserver-sia.database.windows.net";
$connOpts = [
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connOpts);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

$idCodigo = (int)$_POST['idCodigo'];
$idProveedor = (int)$_POST['idProveedor'];
$cantidad = (int)$_POST['cantidad'];
$fecha = $_POST['fecha'];
$idCaja = 1;
$ubicacion = "Almacen";

// 1. Insertar en Entradas
$sqlEntrada = "INSERT INTO Entradas (idCodigo, idProveedor, cantidad, fecha)
               VALUES (?, ?, ?, ?)";
$paramsEntrada = [$idCodigo, $idProveedor, $cantidad, $fecha];
$stmtEntrada = sqlsrv_query($conn, $sqlEntrada, $paramsEntrada);
if ($stmtEntrada === false) {
    die(print_r(sqlsrv_errors(), true));
}

// 2. Insertar herramientas únicas con identificadorUnico
$sqlContador = "SELECT COUNT(*) AS total FROM HerramientasUnicas WHERE idCodigo = ?";
$stmtContador = sqlsrv_query($conn, $sqlContador, [$idCodigo]);
if ($stmtContador === false) {
    die(print_r(sqlsrv_errors(), true));
}
$rowContador = sqlsrv_fetch_array($stmtContador, SQLSRV_FETCH_ASSOC);
$contador = (int)$rowContador['total'];

for ($i = 1; $i <= $cantidad; $i++) {
    $identificadorUnico = $idCodigo . '-' . ($contador + $i);
    $sqlHerramienta = "INSERT INTO HerramientasUnicas (idCodigo, fechaEntrada, estadoActual, observaciones, enInventario, identificadorUnico)
                       VALUES (?, ?, 'Funcional', 'Nueva herramienta', 1, ?)";
    $paramsHerramienta = [$idCodigo, $fecha, $identificadorUnico];
    $stmtHerramienta = sqlsrv_query($conn, $sqlHerramienta, $paramsHerramienta);
    if ($stmtHerramienta === false) {
        die(print_r(sqlsrv_errors(), true));
    }
}

// 3. Insertar o actualizar Inventario
$sql = "SELECT cantidadActual FROM Inventario WHERE idCodigo = ?";
$stmt = sqlsrv_query($conn, $sql, [$idCodigo]);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if ($row) {
    $nueva = $row['cantidadActual'] + $cantidad;
    $sqlUpdate = "UPDATE Inventario SET cantidadActual = ?, ultimaActualizacion = ?
                  WHERE idCodigo = ?";
    $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [$nueva, $fecha, $idCodigo]);
    if ($stmtUpdate === false) {
        die(print_r(sqlsrv_errors(), true));
    }
} else {
    $sqlInsert = "INSERT INTO Inventario (idCodigo, idCaja, cantidadActual, ubicacion, ultimaActualizacion)
                  VALUES (?, ?, ?, ?, ?)";
    $stmtInsert = sqlsrv_query($conn, $sqlInsert, [$idCodigo, $idCaja, $cantidad, $ubicacion, $fecha]);
    if ($stmtInsert === false) {
        die(print_r(sqlsrv_errors(), true));
    }
}

header("Location: ../admnedtetcf.php");
exit();
?>