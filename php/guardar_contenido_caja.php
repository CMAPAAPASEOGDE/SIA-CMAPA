<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$idCaja = intval($_POST['idCaja'] ?? 0);
$codigos = $_POST['codigo'] ?? [];
$cantidades = $_POST['cantidad'] ?? [];

$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) die(print_r(sqlsrv_errors(), true));

// Recorremos los elementos y actualizamos
for ($i = 0; $i < count($codigos); $i++) {
    $codigo = $codigos[$i];
    $cantidad = intval($cantidades[$i]);

    $sql = "UPDATE CajaContenido SET cantidad = ? WHERE idCaja = ? AND idCodigo = ?";
    $stmt = sqlsrv_query($conn, $sql, [$cantidad, $idCaja, $codigo]);

    if ($stmt === false) {
        die("Error al guardar cantidad: " . print_r(sqlsrv_errors(), true));
    }
}

header("Location: boxinspect.php?idCaja=$idCaja&actualizado=1");
exit();
