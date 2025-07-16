<?php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || (int)$_SESSION['rol'] !== 1) {
    header("Location: ../acceso_denegado.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['usuario'])) {
    $usuario = trim($_POST['usuario']);

    // Conexión a la BD
    $server = "tcp:sqlserver-sia.database.windows.net,1433";
    $database = "db_sia";
    $username = "cmapADMIN";
    $passwordDB = "@siaADMN56*";

    try {
        $conn = new PDO("sqlsrv:Server=$server;Database=$database", $username, $passwordDB);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $conn->prepare("SELECT idUsuario, usuario, rol, apodo FROM usuarios WHERE usuario = :usuario");
        $stmt->bindParam(':usuario', $usuario);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Guardar en sesión temporal
            $_SESSION['usr_eliminar'] = $user;
            header("Location: ../admnusrers.php");
            exit();
        } else {
            header("Location: ../admnusrerssrcher.php"); // Usuario no encontrado
            exit();
        }

    } catch (PDOException $e) {
        die("Error al conectar con la BD: " . $e->getMessage());
    }
} else {
    header("Location: ../admnusrerssrch.php");
    exit();
}
