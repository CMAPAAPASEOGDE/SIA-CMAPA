<?php
$serverName = "sqlserver-sia.database.windows.net";
$connOpts = [
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connOpts);

$id = $_GET['id'] ?? 0;
$sql = "SELECT descripcion, linea, sublinea FROM Productos WHERE idCodigo = ?";
$stmt = sqlsrv_query($conn, $sql, [ (int)$id ]);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($row);
