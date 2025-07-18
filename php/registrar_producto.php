<?php
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../index.php");
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

// Recibir los datos del formulario
$codigo = $_POST['codigo'];
$descripcion = $_POST['descripcion'];
$tipo = $_POST['tipo'];
$linea = $_POST['linea'];
$sublin = $_POST['sublinea'];
$unidad = $_POST['unidad'];
$precio = $_POST['precio'];
$stockmax = $_POST['stockmax'];
$reorden = $_POST['reorden'];

// Validar si ya existe el producto
$sql_check = "SELECT 1 FROM Productos WHERE codigo = ?";
$params_check = array($codigo);
$stmt_check = sqlsrv_query($conn, $sql_check, $params_check);

if ($stmt_check === false) {
    die(print_r(sqlsrv_errors(), true));
}

if (sqlsrv_fetch($stmt_check)) {
    // El código ya existe
    header("Location: ../nwentryer.php");
    exit();
}

$estatus = 1;

// Insertar nuevo producto
$sql_insert = "INSERT INTO Productos (codigo, descripcion, tipo, linea, sublinea, unidad, precio, stockMaximo, puntoReorden, estatus)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$params_insert = array(
    $codigo, $descripcion, $tipo, $linea, $sublin, $unidad,
    $precio, $stockmax, $reorden, $estatus
);

$stmt_insert = sqlsrv_query($conn, $sql_insert, $params_insert);

if ($stmt_insert === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Éxito
header("Location: ../nwentrycnf.php");
exit();
