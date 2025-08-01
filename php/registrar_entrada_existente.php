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
$fecha = date('Y-m-d H:i:s');
$idCaja = 1;
$ubicacion = "Almacen";

// Validar stock
$sqlProducto = "SELECT P.stockMaximo, ISNULL(I.cantidadActual, 0) AS cantidadActual FROM Productos P
                LEFT JOIN Inventario I ON P.idCodigo = I.idCodigo WHERE P.idCodigo = ?";
$stmtProducto = sqlsrv_query($conn, [$sqlProducto], [$idCodigo]);
$row = sqlsrv_fetch_array($stmtProducto, SQLSRV_FETCH_ASSOC);

$stockMaximo = $row['stockMaximo'] ?? 0;
$cantidadActual = (int)$row['cantidadActual'];
$nuevaCantidad = $cantidad + $cantidadActual;

if ($stockMaximo > 0 && $nuevaCantidad > $stockMaximo) {
    header("Location: ../exteterr.php");
    exit();
}

// Insertar entrada
$sqlEntrada = "INSERT INTO Entradas (idCodigo, idProveedor, cantidad, fecha) VALUES (?, ?, ?, ?)";
sqlsrv_query($conn, [$sqlEntrada], [$idCodigo, $idProveedor, $cantidad, $fecha]);

// Insertar o actualizar inventario
$sqlCheck = "SELECT 1 FROM Inventario WHERE idCodigo = ?";
$stmtCheck = sqlsrv_query($conn, [$sqlCheck], [$idCodigo]);

if (sqlsrv_fetch($stmtCheck)) {
    $sqlUpdate = "UPDATE Inventario SET cantidadActual = ?, ultimaActualizacion = ?, idCaja = ?, ubicacion = ?
                  WHERE idCodigo = ?";
    sqlsrv_query($conn, [$sqlUpdate], [$nuevaCantidad, $fecha, $idCaja, $ubicacion, $idCodigo]);
} else {
    $sqlInsertInv = "INSERT INTO Inventario (idCodigo, idCaja, cantidadActual, ubicacion, ultimaActualizacion)
                     VALUES (?, ?, ?, ?, ?)";
    sqlsrv_query($conn, [$sqlInsertInv], [$idCodigo, $idCaja, $cantidad, $ubicacion, $fecha]);
}

// Si es herramienta
$sqlTipo = "SELECT tipo FROM Productos WHERE idCodigo = ?";
$stmtTipo = sqlsrv_query($conn, [$sqlTipo], [$idCodigo]);
$rowTipo = sqlsrv_fetch_array($stmtTipo, SQLSRV_FETCH_ASSOC);
if (strtoupper($rowTipo['tipo']) === 'HERRAMIENTA') {
    for ($i = 0; $i < $cantidad; $i++) {
        $sqlHerr = "INSERT INTO HerramientasUnicas (idCodigo, estado, comentario, fechaRegreso, enInventario) 
                    VALUES (?, 'FUNCIONAL', 'INGRESO NUEVO', NULL, 1)";
        sqlsrv_query($conn, [$sqlHerr], [$idCodigo]);
    }
}

header("Location: ../extetcnf.php");
exit();
?>
