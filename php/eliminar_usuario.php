<?php
session_start();

if (!isset($_SESSION['user_id']) || (int)$_SESSION['rol'] !== 1) {
    header("Location: ../acceso_denegado.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['idUsuario'])) {
    $id = (int)$_POST['idUsuario'];

    $server = "tcp:sqlserver-sia.database.windows.net,1433";
    $database = "db_sia";
    $username = "cmapADMIN";
    $passwordDB = "@siaADMN56*";

    try {
        $conn = new PDO("sqlsrv:Server=$server;Database=$database", $username, $passwordDB);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $delete = $conn->prepare("DELETE FROM usuarios WHERE idUsuario = :id");
        $delete->bindParam(':id', $id);
        $delete->execute();

        unset($_SESSION['usr_eliminar']);
        header("Location: ../admnusrerscf.php"); // Usuario eliminado con éxito
        exit();

    } catch (PDOException $e) {
        echo "Error al eliminar: " . $e->getMessage();
    }
} else {
    header("Location: ../admnusrerssrch.php");
    exit();
}
