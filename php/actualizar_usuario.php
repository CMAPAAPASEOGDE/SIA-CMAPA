<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || (int)$_SESSION['rol'] !== 1) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'];
    $rol = (int)$_POST['rol'];
    $nombre = $_POST['apodo'];
    $estatus = isset($_POST['estatus']) ? 1 : 0;
    $password = isset($_POST['password']) ? $_POST['password'] : null;
    $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : null;

    if ($password && $password !== $confirm) {
        header("Location: admnusredtfail.php");
        exit();
    }

    $server = "tcp:sqlserver-sia.database.windows.net,1433";
    $database = "db_sia";
    $username = "cmapADMIN";
    $passwordDB = "@siaADMN56*";

    try {
        $conn = new PDO("sqlsrv:Server=$server;Database=$database", $username, $passwordDB);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($password) {
            $query = $conn->prepare("UPDATE usuarios SET nombre = :nombre, idRol = :rol, estatus = :estatus, contrasena = :contrasena WHERE usuario = :usuario");
            $query->bindParam(':contrasena', $password); // puedes usar password_hash() si quieres usar hash
        } else {
            $query = $conn->prepare("UPDATE usuarios SET nombre = :nombre, idRol = :rol, estatus = :estatus WHERE usuario = :usuario");
        }

        $query->bindParam(':nombre', $nombre);
        $query->bindParam(':rol', $rol);
        $query->bindParam(':estatus', $estatus);
        $query->bindParam(':usuario', $usuario);
        $query->execute();

        unset($_SESSION['editar_usuario']);
        header("Location: admnusredtcf.php");
        exit();
    } catch (PDOException $e) {
        echo "Error de conexión: " . $e->getMessage();
        exit();
    }
}
