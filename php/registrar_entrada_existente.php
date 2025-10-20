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
if ($conn === false) die(print_r(sqlsrv_errors(), true));

$idCodigo    = (int)($_POST['idCodigo'] ?? 0);
$idProveedor = (int)($_POST['idProveedor'] ?? 0);
$cantidad    = (int)($_POST['cantidad'] ?? 0);
$fecha       = $_POST['fecha'] ?? date('Y-m-d H:i:s');
$idCaja      = 1;
$ubicacion   = "Almacen";

$fechaParam = date('Y-m-d H:i:s', strtotime($fecha));

// 0) Info del producto
$sqlInfo = "SELECT codigo, tipo FROM Productos WHERE idCodigo = ?";
$stmtInfo = sqlsrv_query($conn, $sqlInfo, [$idCodigo]);
if ($stmtInfo === false) die(print_r(sqlsrv_errors(), true));
$rowInfo = sqlsrv_fetch_array($stmtInfo, SQLSRV_FETCH_ASSOC);
if (!$rowInfo) die("Producto no encontrado.");

$codigoProducto = $rowInfo['codigo'];
$tipo           = strtolower(trim($rowInfo['tipo'] ?? ''));
$esHerramienta  = in_array($tipo, ['herramienta','herramientas'], true);

if (!sqlsrv_begin_transaction($conn)) {
    die(print_r(sqlsrv_errors(), true));
}

// 1) Insertar en Entradas
$sqlEntrada = "INSERT INTO Entradas (idCodigo, idProveedor, cantidad, fecha)
               VALUES (?, ?, ?, ?)";
$paramsEntrada = [$idCodigo, $idProveedor, $cantidad, $fechaParam];
$stmtEntrada = sqlsrv_query($conn, $sqlEntrada, $paramsEntrada);
if ($stmtEntrada === false) {
    sqlsrv_rollback($conn);
    die(print_r(sqlsrv_errors(), true));
}

// 2) Insertar herramientas únicas con identificadorUnico incremental
if ($esHerramienta && $cantidad > 0) {
    $sqlContador = "SELECT COUNT(*) AS total FROM HerramientasUnicas WHERE idCodigo = ?";
    $stmtContador = sqlsrv_query($conn, $sqlContador, [$idCodigo]);
    if ($stmtContador === false) {
        sqlsrv_rollback($conn);
        die(print_r(sqlsrv_errors(), true));
    }
    $rowContador = sqlsrv_fetch_array($stmtContador, SQLSRV_FETCH_ASSOC);
    $contador = (int)($rowContador['total'] ?? 0);

    for ($i = 1; $i <= $cantidad; $i++) {
        $identificadorUnico = $codigoProducto . '-' . ($contador + $i);

        $sqlHerramienta = "INSERT INTO HerramientasUnicas
            (idCodigo, fechaEntrada, estadoActual, observaciones, enInventario, identificadorUnico)
            VALUES (?, ?, 'Funcional', 'Nueva herramienta', 1, ?)";
        $paramsHerramienta = [$idCodigo, $fechaParam, $identificadorUnico];
        $stmtHerramienta = sqlsrv_query($conn, $sqlHerramienta, $paramsHerramienta);
        if ($stmtHerramienta === false) {
            sqlsrv_rollback($conn);
            die(print_r(sqlsrv_errors(), true));
        }
    }
}

// 3) Inventario (upsert)
$sql = "SELECT cantidadActual FROM Inventario WHERE idCodigo = ?";
$stmt = sqlsrv_query($conn, $sql, [$idCodigo]);
if ($stmt === false) { sqlsrv_rollback($conn); die(print_r(sqlsrv_errors(), true)); }

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if ($row) {
    $nueva = (int)$row['cantidadActual'] + $cantidad;
    $sqlUpdate = "UPDATE Inventario SET cantidadActual = ?, ultimaActualizacion = ? WHERE idCodigo = ?";
    $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [$nueva, $fechaParam, $idCodigo]);
    if ($stmtUpdate === false) { sqlsrv_rollback($conn); die(print_r(sqlsrv_errors(), true)); }
} else {
    $sqlInsert = "INSERT INTO Inventario (idCodigo, idCaja, cantidadActual, ubicacion, ultimaActualizacion)
                  VALUES (?, 13, ?, ?, ?)";
    $stmtInsert = sqlsrv_query($conn, $sqlInsert, [$idCodigo, $idCaja, $cantidad, $ubicacion, $fechaParam]);
    if ($stmtInsert === false) { sqlsrv_rollback($conn); die(print_r(sqlsrv_errors(), true)); }
}

sqlsrv_commit($conn);

header("Location: ../extetcnf.php");
exit();
