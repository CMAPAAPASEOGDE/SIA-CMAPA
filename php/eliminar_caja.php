<?php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$idRol = (int)($_SESSION['rol'] ?? 0);
if ($idRol !== 1) { // Solo administradores pueden eliminar
    header("Location: acceso_denegado.php");
    exit();
}

$idCaja = isset($_GET['idCaja']) ? intval($_GET['idCaja']) : 0;
if ($idCaja <= 0) {
    $_SESSION['error'] = "ID de caja inválido";
    header("Location: boxes.php");
    exit();
}

// Conectar a la BD
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
    die("Error de conexión: " . print_r(sqlsrv_errors(), true));
}

// Iniciar transacción
sqlsrv_begin_transaction($conn);

try {
    // 1. Obtener contenido de la caja para devolver al inventario
    $sqlGetContents = "SELECT idCodigo, cantidad FROM CajaContenido WHERE idCaja = ?";
    $params = [$idCaja];
    $stmtContents = sqlsrv_query($conn, $sqlGetContents, $params);
    
    if ($stmtContents === false) {
        throw new Exception("Error al obtener contenido: " . print_r(sqlsrv_errors(), true));
    }
    
    // 2. Devolver productos al inventario
    while ($row = sqlsrv_fetch_array($stmtContents, SQLSRV_FETCH_ASSOC)) {
        $updateInv = "UPDATE Inventario 
                     SET CantidadActual = CantidadActual + ? 
                     WHERE idCodigo = ?";
        $paramsInv = [$row['cantidad'], $row['idCodigo']];
        $stmtUpdate = sqlsrv_query($conn, $updateInv, $paramsInv);
        
        if ($stmtUpdate === false) {
            throw new Exception("Error al actualizar inventario: " . print_r(sqlsrv_errors(), true));
        }
    }
    
    // 3. Eliminar contenido de la caja
    $sqlDeleteContents = "DELETE FROM CajaContenido WHERE idCaja = ?";
    $stmtDeleteContents = sqlsrv_query($conn, $sqlDeleteContents, $params);
    
    if ($stmtDeleteContents === false) {
        throw new Exception("Error al eliminar contenido: " . print_r(sqlsrv_errors(), true));
    }
    
    // 4. Eliminar la caja
    $sqlDeleteBox = "DELETE FROM CajaRegistro WHERE idCaja = ?";
    $stmtDeleteBox = sqlsrv_query($conn, $sqlDeleteBox, $params);
    
    if ($stmtDeleteBox === false) {
        throw new Exception("Error al eliminar caja: " . print_r(sqlsrv_errors(), true));
    }
    
    // Confirmar todas las operaciones
    sqlsrv_commit($conn);
    
    $_SESSION['success'] = "Caja eliminada correctamente";
    header("Location: boxes.php");
    exit();

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    $_SESSION['error'] = $e->getMessage();
    header("Location: boxinspect.php?idCaja=" . $idCaja);
    exit();
}