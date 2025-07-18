<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../exstentry.php");
    exit();
}

// Conexión a la base de datos
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = array(
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
);
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Recibir datos
$idCodigo = (int)$_POST['idCodigo'];
$idProveedor = (int)$_POST['idProveedor'];
$cantidad = (int)$_POST['cantidad'];
$fecha = date('Y-m-d H:i:s');
$idCaja = 1;
$ubicacion = "Almacen";

// 1. Obtener cantidad actual e info del producto
$sqlProducto = "SELECT P.stockMaximo, ISNULL(I.cantidadActual, 0) AS cantidadActual
                FROM Productos P
                LEFT JOIN Inventario I ON P.idCodigo = I.idCodigo
                WHERE P.idCodigo = ?";
$stmtProducto = sqlsrv_query($conn, $sqlProducto, array($idCodigo));
if ($stmtProducto === false) {
    die(print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmtProducto, SQLSRV_FETCH_ASSOC);
if (!$row) {
    header("Location: ../exteterr.php");
    exit();
}

$stockMaximo = (int)$row['stockMaximo'];
$cantidadActual = (int)$row['cantidadActual'];
$nuevaCantidad = $cantidad + $cantidadActual;

if ($nuevaCantidad > $stockMaximo) {
    // Excede el stock máximo permitido
    header("Location: ../exteterr.php");
    exit();
}

// 2. Registrar entrada
$sqlEntrada = "INSERT INTO Entradas (idCodigo, idProveedor, cantidad, fechaEntrada)
               VALUES (?, ?, ?, ?)";
$paramsEntrada = array($idCodigo, $idProveedor, $cantidad, $fecha);
$stmtEntrada = sqlsrv_query($conn, $sqlEntrada, $paramsEntrada);
if ($stmtEntrada === false) {
    die(print_r(sqlsrv_errors(), true));
}

// 3. Actualizar inventario
$sqlCheckInv = "SELECT 1 FROM Inventario WHERE idCodigo = ?";
$stmtCheck = sqlsrv_query($conn, array($idCodigo));
if ($stmtCheck === false) {
    die(print_r(sqlsrv_errors(), true));
}

if (sqlsrv_fetch($stmtCheck)) {
    // Actualizar
    $sqlUpdate = "UPDATE Inventario 
                  SET cantidadActual = ?, ultimaActualizacion = ?, idCaja = ?, ubicacion = ?
                  WHERE idCodigo = ?";
    $paramsUpdate = array($nuevaCantidad, $fecha, $idCaja, $ubicacion, $idCodigo);
    $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);
    if ($stmtUpdate === false) {
        die(print_r(sqlsrv_errors(), true));
    }
} else {
    // Insertar
    $sqlInsertInv = "INSERT INTO Inventario (idCodigo, idCaja, cantidadActual, ubicacion, ultimaActualizacion)
                     VALUES (?, ?, ?, ?, ?)";
    $paramsInsert = array($idCodigo, $idCaja, $cantidad, $ubicacion, $fecha);
    $stmtInsert = sqlsrv_query($conn, $sqlInsertInv, $paramsInsert);
    if ($stmtInsert === false) {
        die(print_r(sqlsrv_errors(), true));
    }
}

// 4. Redirigir a confirmación
header("Location: ../extetcnf.php");
exit();
