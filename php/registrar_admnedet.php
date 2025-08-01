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

$idCodigo = (int)$_POST['idCodigo'];
$idProveedor = (int)$_POST['idProveedor'];
$cantidad = (int)$_POST['cantidad'];
$fecha = $_POST['fecha'];
$idCaja = 1;
$ubicacion = "Almacen";

// Insertar en Entradas
$sqlEntrada = "INSERT INTO Entradas (idCodigo, idProveedor, cantidad, fecha) VALUES (?, ?, ?, ?)";
$paramsEntrada = [$idCodigo, $idProveedor, $cantidad, $fecha];
sqlsrv_query($conn, $sqlEntrada, $paramsEntrada);

// Insertar o actualizar Inventario
$sql = "SELECT cantidadActual FROM Inventario WHERE idCodigo = ?";
$stmt = sqlsrv_query($conn, $sql, [$idCodigo]);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if ($row) {
    $nueva = $row['cantidadActual'] + $cantidad;
    $sqlUpdate = "UPDATE Inventario SET cantidadActual = ?, ultimaActualizacion = ?, idCaja = ?, ubicacion = ? WHERE idCodigo = ?";
    sqlsrv_query($conn, $sqlUpdate, [$nueva, $fecha, $idCaja, $ubicacion, $idCodigo]);
} else {
    $sqlInsert = "INSERT INTO Inventario (idCodigo, idCaja, cantidadActual, ubicacion, ultimaActualizacion) VALUES (?, ?, ?, ?, ?)";
    sqlsrv_query($conn, $sqlInsert, [$idCodigo, $idCaja, $cantidad, $ubicacion, $fecha]);
}

// Verificar si es herramienta
$sqlTipo = "SELECT tipo FROM Productos WHERE idCodigo = ?";
$stmtTipo = sqlsrv_query($conn, [$sqlTipo], [$idCodigo]);
$rowTipo = sqlsrv_fetch_array($stmtTipo, SQLSRV_FETCH_ASSOC);
if (strtoupper($rowTipo['tipo']) === 'HERRAMIENTA') {
    for ($i = 0; $i < $cantidad; $i++) {
        $sqlHerr = "INSERT INTO HerramientasUnicas (idCodigo, estado, comentario, fechaRegreso, enInventario) 
                    VALUES (?, 'FUNCIONAL', 'INGRESO NUEVO', NULL, 1)";
        sqlsrv_query($conn, $sqlHerr, [$idCodigo]);
    }
}

header("Location: ../admnedtetcf.php");
exit();
?>
