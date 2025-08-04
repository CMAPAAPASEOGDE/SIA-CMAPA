<?php
session_start();
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

$response = ['success' => false, 'message' => ''];

try {
    // Verificar datos
    $required = ['idHerramienta', 'estado', 'fechaRetorno'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Campo requerido: $field");
        }
    }

    $idHerramienta = (int)$_POST['idHerramienta'];
    $observaciones = $_POST['observaciones'] ?? '';
    $estado = $_POST['estado'];
    $fechaRetorno = $_POST['fechaRetorno'];
    $registradoPor = (int)$_POST['registradoPor'];

    // Iniciar transacción
    if (sqlsrv_begin_transaction($conn) === false) {
        throw new Exception("No se pudo iniciar transacción");
    }

    // 1. Insertar en Devoluciones
    $sql1 = "INSERT INTO Devoluciones (idHerramienta, observaciones, estado, fechaRetorno, registradoPor)
             VALUES (?, ?, ?, ?, ?)";
    $params1 = array($idHerramienta, $observaciones, $estado, $fechaRetorno, $registradoPor);
    $stmt1 = sqlsrv_query($conn, $sql1, $params1);
    if ($stmt1 === false) {
        throw new Exception("Error en devolución: " . print_r(sqlsrv_errors(), true));
    }

    // 2. Actualizar HerramientasUnicas
    $sql2 = "UPDATE HerramientasUnica 
             SET enInventario = 1, estadoActual = ?, observaciones = ?
             WHERE idHerramienta = ?";
    $params2 = array($estado, $observaciones, $idHerramienta);
    $stmt2 = sqlsrv_query($conn, $sql2, $params2);
    if ($stmt2 === false) {
        throw new Exception("Error en actualización: " . print_r(sqlsrv_errors(), true));
    }

    // Confirmar transacción
    sqlsrv_commit($conn);
    $response['success'] = true;

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    $response['message'] = $e->getMessage();
}

// Liberar recursos
if (isset($stmt1)) sqlsrv_free_stmt($stmt1);
if (isset($stmt2)) sqlsrv_free_stmt($stmt2);
sqlsrv_close($conn);

header('Content-Type: application/json');
echo json_encode($response);
?>