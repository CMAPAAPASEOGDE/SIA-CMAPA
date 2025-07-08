<?php
session_start();

// Datos de conexión
$server   = "tcp:sqlserver-sia.database.windows.net,1433";
$database = "db_sia";
$username = "cmapADMIN"; // usuario SQL
$password = "@siaADMN56*"; // contraseña real

try {
    // Conexión con PDO a SQL Server
    $conn = new PDO("sqlsrv:Server=$server;Database=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
