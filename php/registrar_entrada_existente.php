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

// 1. Obtener info del producto e inventario
$sqlProducto = "SELECT P.codigo, P.stockMaximo, ISNULL(I.cantidadActual, 0) AS cantidadActual
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

$codigoProducto = $row['codigo'];
$stockMaximo = $row['stockMaximo'] ?? 0;
$cantidadActual = (int)$row['cantidadActual'];

// Obtener tipo del producto
$sqlTipo = "SELECT tipo FROM Productos WHERE idCodigo = ?";
$stmtTipo = sqlsrv_query($conn, $sqlTipo, [$idCodigo]);
if ($stmtTipo === false) {
    die(print_r(sqlsrv_errors(), true));
}
$rowTipo = sqlsrv_fetch_array($stmtTipo, SQLSRV_FETCH_ASSOC);
$esHerramienta = (strtolower($rowTipo['tipo']) === 'herramienta');

$stockMaximo = $row['stockMaximo'] ?? 0;
$cantidadActual = (int)$row['cantidadActual'];
$nuevaCantidad = $cantidad + $cantidadActual;

if ($stockMaximo > 0 && $nuevaCantidad > $stockMaximo) {
    // Excede el stock máximo permitido
    header("Location: ../exteterr.php");
    exit();
}

// 2. Registrar entrada
$sqlEntrada = "INSERT INTO Entradas (idCodigo, idProveedor, cantidad, fecha)
               VALUES (?, ?, ?, ?)";
$paramsEntrada = array($idCodigo, $idProveedor, $cantidad, $fecha);
$stmtEntrada = sqlsrv_query($conn, $sqlEntrada, $paramsEntrada);
if ($stmtEntrada === false) {
    die(print_r(sqlsrv_errors(), true));
}

// 3. Verificar si ya existe en Inventario
$sqlCheckInv = "SELECT 1 FROM Inventario WHERE idCodigo = ?";
$stmtCheck = sqlsrv_query($conn, $sqlCheckInv, array($idCodigo));
if ($stmtCheck === false) {
    die(print_r(sqlsrv_errors(), true));
}

$existeEnInventario = sqlsrv_fetch($stmtCheck);

// 4. Insertar herramientas únicas (solo si es herramienta)
if ($esHerramienta) {
    $sqlContador = "SELECT COUNT(*) AS total FROM HerramientasUnicas WHERE idCodigo = ?";
    $stmtContador = sqlsrv_query($conn, $sqlContador, [$idCodigo]);
    if ($stmtContador === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    $rowContador = sqlsrv_fetch_array($stmtContador, SQLSRV_FETCH_ASSOC);
    $contador = (int)$rowContador['total'];

    for ($i = 1; $i <= $cantidad; $i++) {
        $identificadorUnico = $codigoProducto. '-' . ($contador + $i);
        $sqlHerramienta = "INSERT INTO HerramientasUnicas (idCodigo, fechaEntrada, estadoActual, observaciones, enInventario, identificadorUnico)
                           VALUES (?, ?, 'Funcional', 'Nueva herramienta', 1, ?)";
        $paramsHerramienta = [$idCodigo, $fecha, $identificadorUnico];
        $stmtHerramienta = sqlsrv_query($conn, $sqlHerramienta, $paramsHerramienta);
        if ($stmtHerramienta === false) {
            die(print_r(sqlsrv_errors(), true));
        }
    }
}

// 5. Actualizar inventario
if ($existeEnInventario) {
    // Actualizar cantidad existente
    $sqlUpdate = "UPDATE Inventario 
                  SET cantidadActual = ?, ultimaActualizacion = ?
                  WHERE idCodigo = ?";
    $paramsUpdate = array($nuevaCantidad, $fecha, $idCodigo);
    $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);
    if ($stmtUpdate === false) {
        die(print_r(sqlsrv_errors(), true));
    }
} else {
    // Insertar nuevo registro
    $sqlInsertInv = "INSERT INTO Inventario (idCodigo, idCaja, cantidadActual, ubicacion, ultimaActualizacion)
                     VALUES (?, ?, ?, ?, ?)";
    $paramsInsert = array($idCodigo, $idCaja, $cantidad, $ubicacion, $fecha);
    $stmtInsert = sqlsrv_query($conn, $sqlInsertInv, $paramsInsert);
    if ($stmtInsert === false) {
        die(print_r(sqlsrv_errors(), true));
    }
}

// 6. Confirmación
header("Location: ../extetcnf.php");
exit();
?>