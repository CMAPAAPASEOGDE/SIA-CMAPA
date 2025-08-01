<?php
session_start();

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
$rpu = $_POST['rpuUsuario'] ?? '';
$orden = $_POST['numeroOrden'] ?? '';
$comentarios = $_POST['comentarios'] ?? '';
$idOperador = (int) ($_POST['idOperador'] ?? 0);
$fecha = date('Y-m-d H:i:s');

// Validaciones generales
if (strlen($rpu) !== 12 || !ctype_digit($rpu) || empty($orden) || $idOperador === 0) {
    header("Location: ../exitorder.php");
    exit();
}

// Obtener los elementos de salida
$elementos = $_POST['elementos'] ?? [];
if (empty($elementos) || !is_array($elementos)) {
    header("Location: ../exitorder.php");
    exit();
}

foreach ($elementos as $el) {
    $idCodigo = (int) ($el['idCodigo'] ?? 0);
    $cantidad = (int) ($el['cantidad'] ?? 0);

    if ($idCodigo === 0 || $cantidad <= 0) {
        header("Location: ../exitorder.php");
        exit();
    }

    // Verificar stock actual
    $sqlCheck = "SELECT cantidadActual FROM Inventario WHERE idCodigo = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$idCodigo]);
    if ($stmtCheck === false) die(print_r(sqlsrv_errors(), true));

    $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    if (!$row || $row['cantidadActual'] < $cantidad) {
        header("Location: ../exitorder2.php");
        exit();
    }

    // Obtener herramientas únicas para actualizar
    $sqlGetTools = "SELECT TOP (?) idHerramienta 
                    FROM HerramientasUnicas 
                    WHERE idCodigo = ? AND enInventario = 1 
                    ORDER BY idHerramienta ASC";
    $paramsGetTools = [$cantidad, $idCodigo];
    $stmtGetTools = sqlsrv_query($conn, $sqlGetTools, $paramsGetTools);
    if ($stmtGetTools === false) die(print_r(sqlsrv_errors(), true));
    
    $toolIds = [];
    while ($rowTool = sqlsrv_fetch_array($stmtGetTools, SQLSRV_FETCH_ASSOC)) {
        $toolIds[] = $rowTool['idHerramienta'];
    }
    
    // Si no hay suficientes herramientas, error
    if (count($toolIds) < $cantidad) {
        header("Location: ../exitorder2.php");
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
    
    // Actualizar herramientas únicas
    foreach ($toolIds as $toolId) {
        $sqlUpdateTool = "UPDATE HerramientasUnicas 
                          SET enInventario = 0 
                          WHERE idHerramienta = ?";
        $stmtUpdateTool = sqlsrv_query($conn, $sqlUpdateTool, [$toolId]);
        if ($stmtUpdateTool === false) die(print_r(sqlsrv_errors(), true));
    }
}

// Redirigir a confirmación
header("Location: ../exitordcnf.php");
exit();
?>