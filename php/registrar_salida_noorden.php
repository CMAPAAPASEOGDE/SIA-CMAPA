<?php
session_start();

// Verifica que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../exitnoorder.php");
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
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Obtener datos del formulario
$area = trim($_POST['areaSolicitante'] ?? '');
$encargado = trim($_POST['encargadoArea'] ?? '');
$comentarios = trim($_POST['comentarios'] ?? '');
$fecha = date('Y-m-d H:i:s');

if ($area === '' || $encargado === '' || $comentarios === '') {
    header("Location: ../exitnoorder.php");
    exit();
}

// Elementos
$elementos = $_POST['elementos'] ?? [];
if (empty($elementos)) {
    header("Location: ../exitnoorder.php");
    exit();
}

// Verificar stock antes de registrar
foreach ($elementos as $item) {
    $idCodigo = (int)($item['idCodigo'] ?? 0);
    $cantidad = (int)($item['cantidad'] ?? 0);

    if ($idCodigo === 0 || $cantidad <= 0) {
        header("Location: ../exitnoorder.php");
        exit();
    }

    $sqlCheck = "SELECT cantidadActual FROM Inventario WHERE idCodigo = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$idCodigo]);
    if ($stmtCheck === false) die(print_r(sqlsrv_errors(), true));

    $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    if (!$row || $row['cantidadActual'] < $cantidad) {
        header("Location: ../exitnoorder2.php");
        exit();
    }
}

// Si hay stock suficiente, registrar en SalidaSinorden y actualizar Inventario
foreach ($elementos as $item) {
    $idCodigo = (int)$item['idCodigo'];
    $cantidad = (int)$item['cantidad'];

    // Registrar salida
    $sqlInsert = "INSERT INTO SalidaSinorden (areaSolicitante, encargadoArea, fecha, comentarios, idCodigo, cantidad)
                  VALUES (?, ?, ?, ?, ?, ?)";
    $paramsInsert = [$area, $encargado, $fecha, $comentarios, $idCodigo, $cantidad];
    $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);
    if ($stmtInsert === false) die(print_r(sqlsrv_errors(), true));

    // Actualizar inventario
    $sqlUpdate = "UPDATE Inventario 
                  SET cantidadActual = cantidadActual - ?, 
                      ultimaActualizacion = ?
                  WHERE idCodigo = ?";
    $paramsUpdate = [$cantidad, $fecha, $idCodigo];
    $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);
    if ($stmtUpdate === false) die(print_r(sqlsrv_errors(), true));
}

// Todo salió bien
header("Location: ../exitnoordcnf.php");
exit();
