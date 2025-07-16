<?php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || (int)$_SESSION['rol'] !== 1) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);

    // Conexión
    $server = "tcp:sqlserver-sia.database.windows.net,1433";
    $database = "db_sia";
    $username = "cmapADMIN";
    $passwordDB = "@siaADMN56*";

    try {
        $conn = new PDO("sqlsrv:Server=$server;Database=$database", $username, $passwordDB);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $conn->prepare("SELECT * FROM usuarios WHERE usuario = :usuario");
        $query->bindParam(':usuario', $usuario);
        $query->execute();

        if ($query->rowCount() > 0) {
            $user = $query->fetch(PDO::FETCH_ASSOC);
            $_SESSION['editar_usuario'] = $user;
            header("Location: admnusredt.php");
            exit();
        } else {
            header("Location: ../admnusredsrcher.php"); // Usuario no encontrado
            exit();
        }
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
} else {
    header("Location: ../admnusredsrch.php");
    exit();
}
