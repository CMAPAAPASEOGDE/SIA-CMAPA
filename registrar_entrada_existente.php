<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// === LOG simple a archivo ===
function logerr($msg){
    @file_put_contents(__DIR__ . "/../_logs/entradas.log",
        "[".date('Y-m-d H:i:s')."] ".$msg."\n", FILE_APPEND);
}

$serverName = "sqlserver-sia.database.windows.net";
$connOpts = [
    "Database" => "db_sia",
    "Uid"      => "cmapADMIN",
    "PWD"      => "@siaADMN56*",
    "Encrypt"  => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connOpts);
if ($conn === false) {
    logerr("conexion: ".print_r(sqlsrv_errors(), true));
    http_response_code(500); exit("DB error");
}

// ---------- Lee/valida POST ----------
$idCodigo    = (int)($_POST['idCodigo'] ?? 0);
$idProveedor = trim((string)($_POST['idProveedor'] ?? ''));
$cantidad    = (int)($_POST['cantidad'] ?? 0);
$fechaRaw    = $_POST['fecha'] ?? date('Y-m-d');
if ($idCodigo <= 0 || $cantidad <= 0) {
    http_response_code(400); exit("Datos inválidos");
}

// proveedor opcional: si viene vacío o 0 => NULL
$proveedorNullable = null;
if ($idProveedor !== '' && (int)$idProveedor > 0) {
    $proveedorNullable = (int)$idProveedor;
}

$fechaParam = date('Y-m-d H:i:s', strtotime($fechaRaw));
$idCaja    = 1;
$ubicacion = "Almacen";

// 0) Info del producto
$sqlInfo = "SELECT codigo, LOWER(LTRIM(RTRIM(tipo))) AS tipo FROM Productos WHERE idCodigo = ?";
$stmtInfo = sqlsrv_query($conn, $sqlInfo, [$idCodigo]);
if ($stmtInfo === false) {
    logerr("infoProducto: ".print_r(sqlsrv_errors(), true));
    http_response_code(500); exit("DB error");
}
$rowInfo = sqlsrv_fetch_array($stmtInfo, SQLSRV_FETCH_ASSOC);
if (!$rowInfo) { http_response_code(404); exit("Producto no encontrado"); }

$codigoProducto = $rowInfo['codigo'];
$tipo           = $rowInfo['tipo'] ?? '';
$esHerramienta  = in_array($tipo, ['herramienta','herramientas'], true);

if (!sqlsrv_begin_transaction($conn)) {
    logerr("begin: ".print_r(sqlsrv_errors(), true));
    http_response_code(500); exit("DB error");
}

// 1) Entradas (si proveedor es NULL, inserta NULL)
if ($proveedorNullable === null) {
    $sqlEntrada = "INSERT INTO Entradas (idCodigo, idProveedor, cantidad, fecha)
                   VALUES (?, NULL, ?, ?)";
    $paramsEntrada = [$idCodigo, $cantidad, $fechaParam];
} else {
    $sqlEntrada = "INSERT INTO Entradas (idCodigo, idProveedor, cantidad, fecha)
                   VALUES (?, ?, ?, ?)";
    $paramsEntrada = [$idCodigo, $proveedorNullable, $cantidad, $fechaParam];
}
$stmtEntrada = sqlsrv_query($conn, $sqlEntrada, $paramsEntrada);
if ($stmtEntrada === false) {
    logerr("insertEntrada: ".print_r(sqlsrv_errors(), true)." | params=".json_encode($paramsEntrada));
    sqlsrv_rollback($conn);
    http_response_code(500); exit("Error al registrar entrada");
}

// 2) Herramientas únicas (solo si el producto es herramienta)
if ($esHerramienta && $cantidad > 0) {
    $sqlContador = "SELECT COUNT(*) AS total FROM HerramientasUnicas WHERE idCodigo = ?";
    $stmtContador = sqlsrv_query($conn, $sqlContador, [$idCodigo]);
    if ($stmtContador === false) {
        logerr("contadorHU: ".print_r(sqlsrv_errors(), true));
        sqlsrv_rollback($conn);
        http_response_code(500); exit("DB error");
    }
    $rowContador = sqlsrv_fetch_array($stmtContador, SQLSRV_FETCH_ASSOC);
    $contador = (int)($rowContador['total'] ?? 0);

    for ($i = 1; $i <= $cantidad; $i++) {
        $identificadorUnico = $codigoProducto . '-' . ($contador + $i);
        $sqlHerramienta = "INSERT INTO HerramientasUnicas
           (idCodigo, fechaEntrada, estadoActual, observaciones, enInventario, identificadorUnico)
           VALUES (?, ?, 'Funcional', 'Nueva herramienta', 1, ?)";
        $paramsHerr = [$idCodigo, $fechaParam, $identificadorUnico];
        $stmtHerr = sqlsrv_query($conn, $sqlHerramienta, $paramsHerr);
        if ($stmtHerr === false) {
            logerr("insertHU: ".print_r(sqlsrv_errors(), true)." | ".$identificadorUnico);
            sqlsrv_rollback($conn);
            http_response_code(500); exit("DB error");
        }
    }
}

// 3) Upsert Inventario
$sql = "SELECT cantidadActual FROM Inventario WHERE idCodigo = ?";
$stmt = sqlsrv_query($conn, $sql, [$idCodigo]);
if ($stmt === false) {
    logerr("selInv: ".print_r(sqlsrv_errors(), true));
    sqlsrv_rollback($conn); http_response_code(500); exit("DB error");
}
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if ($row) {
    $nueva = (int)$row['cantidadActual'] + $cantidad;
    $sqlUpdate = "UPDATE Inventario SET cantidadActual = ?, ultimaActualizacion = ? WHERE idCodigo = ?";
    $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [$nueva, $fechaParam, $idCodigo]);
    if ($stmtUpdate === false) {
        logerr("updInv: ".print_r(sqlsrv_errors(), true));
        sqlsrv_rollback($conn); http_response_code(500); exit("DB error");
    }
} else {
    $sqlInsert = "INSERT INTO Inventario (idCodigo, idCaja, cantidadActual, ubicacion, ultimaActualizacion)
                  VALUES (?, ?, ?, ?, ?)";
    $stmtInsert = sqlsrv_query($conn, $sqlInsert, [$idCodigo, $idCaja, $cantidad, $ubicacion, $fechaParam]);
    if ($stmtInsert === false) {
        logerr("insInv: ".print_r(sqlsrv_errors(), true));
        sqlsrv_rollback($conn); http_response_code(500); exit("DB error");
    }
}

sqlsrv_commit($conn);
header("Location: extetcnf.php");
exit();
