<?php
session_start();

// Verifica que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../exitorder.php");
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

// Obtener valores generales
$rpu = $_POST['rpu'] ?? '';
$orden = $_POST['orden'] ?? '';
$comentarios = $_POST['comentarios'] ?? '';
$idOperador = (int) ($_POST['idOperador'] ?? 0);
$fecha = date('Y-m-d H:i:s');

// Validaciones generales
if (strlen($rpu) !== 12 || !is_numeric($rpu) || empty($orden) || $idOperador === 0) {
    header("Location: ../exitorder.php");
    exit();
}

// Obtener arrays de productos
$idCodigos = $_POST['idCodigo'] ?? [];
$cantidades = $_POST['cantidad'] ?? [];

if (count($idCodigos) === 0 || count($cantidades) === 0 || count($idCodigos) !== count($cantidades)) {
    header("Location: ../exitorder.php");
    exit();
}

// Procesar cada elemento
foreach ($idCodigos as $i => $idCodigo) {
    $idCodigo = (int) $idCodigo;
    $cantidad = (int) $cantidades[$i];

    if ($idCodigo === 0 || $cantidad <= 0) {
        header("Location: ../exitorder.php");
        exit();
    }

    // Verificar inventario
    $sqlCheck = "SELECT cantidadActual FROM Inventario WHERE idCodigo = ?";
    $stmtCheck = sqlsrv_query($conn, [$sqlCheck, [$idCodigo]]);
    if ($stmtCheck === false) die(print_r(sqlsrv_errors(), true));

    $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    if (!$row || $row['cantidadActual'] < $cantidad) {
        header("Location: ../exitorder2.php"); // No hay stock suficiente
        exit();
    }

    // Registrar salida
    $sqlInsert = "INSERT INTO Salidas (rpuUsuario, numeroOrden, comentarios, idOperador, idCodigo, cantidad, fechaSalida)
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
    $paramsInsert = [$rpu, $orden, $comentarios, $idOperador, $idCodigo, $cantidad, $fecha];
    $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);
    if ($stmtInsert === false) die(print_r(sqlsrv_errors(), true));

    // Actualizar inventario
    $nuevaCantidad = $row['cantidadActual'] - $cantidad;
    $sqlUpdate = "UPDATE Inventario SET cantidadActual = ?, ultimaActualizacion = ? WHERE idCodigo = ?";
    $paramsUpdate = [$nuevaCantidad, $fecha, $idCodigo];
    $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);
    if ($stmtUpdate === false) die(print_r(sqlsrv_errors(), true));
}

// Todo correcto
header("Location: ../exitordcnf.php");
exit();
?>
