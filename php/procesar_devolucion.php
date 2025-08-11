<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']); exit();
}

$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1, 2], true)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']); exit();
}

// Conexión SQL Server
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
    echo json_encode(['success' => false, 'message' => 'Error de conexión: ' . print_r(sqlsrv_errors(), true)]); 
    exit();
}

// Obtener y validar datos
$idHerramienta = trim($_POST['idHerramienta'] ?? '');
$identificadorUnico = trim($_POST['identificadorUnico'] ?? '');
$observaciones = trim($_POST['observaciones'] ?? '');
$estado = trim($_POST['estado'] ?? '');
$fechaRetorno = $_POST['fechaRetorno'] ?? date('Y-m-d');
$registradoPor = (int)($_POST['registradoPor'] ?? $idRol);

// Verificar si el usuario existe
$sqlCheckUser = "SELECT COUNT(*) AS user_exists FROM Usuarios WHERE idUsuario = ?";
$paramsCheckUser = array($registradoPor);
$stmtCheckUser = sqlsrv_query($conn, $sqlCheckUser, $paramsCheckUser);

if ($stmtCheckUser === false) {
    echo json_encode(['success' => false, 'message' => 'Error al verificar usuario']);
    exit();
}

$userCheck = sqlsrv_fetch_array($stmtCheckUser, SQLSRV_FETCH_ASSOC);
if ($userCheck['user_exists'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Usuario no válido: ' . $registradoPor]);
    exit();
}

if (empty($idHerramienta)) {
    echo json_encode(['success' => false, 'message' => 'ID de herramienta requerido']); exit();
}

if (empty($estado)) {
    echo json_encode(['success' => false, 'message' => 'Estado requerido']); exit();
}

// Convertir a formato DateTime
$fechaRetornoDT = date_create($fechaRetorno);
if (!$fechaRetornoDT) {
    $fechaRetornoDT = date_create();
}
$fechaRetornoSQL = $fechaRetornoDT->format('Y-m-d H:i:s');

// Iniciar transacción
if (!sqlsrv_begin_transaction($conn)) {
    echo json_encode(['success' => false, 'message' => 'No se pudo iniciar transacción']); 
    exit();
}

try {
    // 1. Verificar existencia y estado de la herramienta
    $sqlVerificar = "SELECT 
                        idHerramienta, 
                        enInventario,
                        idCodigo,
                        CAST(idHerramienta AS VARCHAR(36)) AS idHerramientaStr
                     FROM HerramientasUnicas 
                     WHERE idHerramienta = ?";
    
    $paramsVerificar = array($idHerramienta);
    $stmtVerificar = sqlsrv_query($conn, $sqlVerificar, $paramsVerificar);
    
    if ($stmtVerificar === false) {
        throw new Exception('Error al verificar herramienta: ' . print_r(sqlsrv_errors(), true));
    }
    
    $herramientaInfo = sqlsrv_fetch_array($stmtVerificar, SQLSRV_FETCH_ASSOC);
    
    if (!$herramientaInfo) {
        throw new Exception('Herramienta no encontrada con ID: ' . $idHerramienta);
    }
    
    // 2. Si ya está en inventario, no se puede devolver
    if ($herramientaInfo['enInventario'] == 1) {
        throw new Exception('La herramienta ya está en inventario');
    }
    
    $idCodigo = $herramientaInfo['idCodigo'];
    $idHerramientaStr = $herramientaInfo['idHerramientaStr'];

    // 3. Actualizar herramienta como devuelta
    $sqlActualizarHerramienta = "UPDATE HerramientasUnicas 
                                 SET enInventario = 1, 
                                     estadoActual = ?
                                 WHERE idHerramienta = ?";
    
    $paramsActualizar = array($estado, $idHerramienta);
    $stmtActualizar = sqlsrv_query($conn, $sqlActualizarHerramienta, $paramsActualizar);
    
    if ($stmtActualizar === false) {
        throw new Exception('Error al actualizar herramienta: ' . print_r(sqlsrv_errors(), true));
    }
    
    // 4. Registrar la devolución
    $sqlInsertarDevolucion = "INSERT INTO Devoluciones (
                                idHerramienta, 
                                observaciones, 
                                estado, 
                                fechaRetorno, 
                                registradoPor
                              ) VALUES (?, ?, ?, ?, ?)";
    
    $paramsDevolucion = array(
        $idHerramienta,
        $observaciones,
        $estado,
        $fechaRetornoSQL,
        $registradoPor
    );
    
    $stmtDevolucion = sqlsrv_query($conn, $sqlInsertarDevolucion, $paramsDevolucion);
    
    if ($stmtDevolucion === false) {
        throw new Exception('Error al registrar devolución: ' . print_r(sqlsrv_errors(), true));
    }
    
    // 5. Actualizar inventario
    $sqlActualizarInventario = "UPDATE Inventario 
                                SET cantidadActual = cantidadActual + 1,
                                    ultimaActualizacion = ?
                                WHERE idCodigo = ?";
    
    $paramsInventario = array(date('Y-m-d H:i:s'), $idCodigo);
    $stmtInventario = sqlsrv_query($conn, $sqlActualizarInventario, $paramsInventario);
    
    if ($stmtInventario === false) {
        throw new Exception('Error al actualizar inventario: ' . print_r(sqlsrv_errors(), true));
    }
    
    // Si todo sale bien, confirmar transacción
    sqlsrv_commit($conn);
    echo json_encode(['success' => true]);

} catch (Exception $ex) {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
} finally {
    // Liberar recursos
    if (isset($stmtVerificar)) sqlsrv_free_stmt($stmtVerificar);
    if (isset($stmtActualizar)) sqlsrv_free_stmt($stmtActualizar);
    if (isset($stmtDevolucion)) sqlsrv_free_stmt($stmtDevolucion);
    if (isset($stmtInventario)) sqlsrv_free_stmt($stmtInventario);
    sqlsrv_close($conn);
}