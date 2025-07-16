<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $rol = $_POST['rol'];
    $apodo = $_POST['apodo'];

    if ($password !== $confirm) {
        header("Location: ../admnusrnwer2.php");
        exit();
    } 

    // Conexión a base de datos
    $server = "tcp:sqlserver-sia.database.windows.net,1433";
    $database = "db_sia";
    $username = "cmapADMIN";
    $passwordDB = "@siaADMN56*";

    try {
        $conn = new PDO("sqlsrv:Server=$server;Database=$database", $username, $passwordDB);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Verificar si el usuario ya existe
        $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = :usuario");
        $stmt->bindParam(':usuario', $usuario);
        $stmt->execute();
        $existe = $stmt->fetchColumn();

        if ($existe > 0) {
            header("Location: ../admnusrnwer.php");
            exit();
        }

        // Insertar nuevo usuario
        $stmt = $conn->prepare("INSERT INTO usuarios (usuario, contrasena, apodo, idRol, estatus)
                                VALUES (:usuario, :contrasena, :apodo, :rol, 1)");
        $stmt->bindParam(':usuario', $usuario);
        $stmt->bindParam(':contrasena', $password); // ¡sin hash por ahora!
        $stmt->bindParam(':apodo', $apodo);
        $stmt->bindParam(':rol', $rol);
        $stmt->execute();

        header("Location: ../admnusernwcf.php");
        exit();
    } catch (PDOException $e) {
        echo "Error en base de datos: " . $e->getMessage();
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
