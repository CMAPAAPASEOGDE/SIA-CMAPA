<?php
session_start();

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

$response = ['success' => false, 'message' => ''];

try {
    // Verificar datos obligatorios
    $required = ['idHerramienta', 'estado', 'fechaRetorno', 'registradoPor'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Faltan campos obligatorios: $field");
        }
    }

    $idHerramienta = (int)$_POST['idHerramienta'];
    $observaciones = $_POST['observaciones'] ?? '';
    $estado = $_POST['estado'];
    $fechaRetorno = $_POST['fechaRetorno'];
    $registradoPor = (int)$_POST['registradoPor'];

    // Iniciar transacción
    $conn->begin_transaction();

    // 1. Insertar en Devoluciones
    $stmt = $conn->prepare("INSERT INTO Devoluciones 
        (idHerramienta, observaciones, estado, fechaRetorno, registradoPor)
        VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $idHerramienta, $observaciones, $estado, $fechaRetorno, $registradoPor);
    $stmt->execute();

    // 2. Actualizar HerramientasUnicas
    $stmt = $conn->prepare("UPDATE HerramientasUnicas 
        SET enInventario = 1, estadoActual = ?, observaciones = ?
        WHERE idHerramienta = ?");
    $stmt->bind_param("ssi", $estado, $observaciones, $idHerramienta);
    $stmt->execute();

    $conn->commit();
    $response['success'] = true;

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
} finally {
    $stmt->close();
    $conn->close();
}

header('Content-Type: application/json');
echo json_encode($response);
?>