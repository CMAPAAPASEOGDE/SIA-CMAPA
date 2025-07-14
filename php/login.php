<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Datos de conexión
    $server   = "tcp:sqlserver-sia.database.windows.net,1433";
    $database = "db_sia";
    $username = "cmapADMIN";
    $passwordDB = "@siaADMN56*"; // Renombrado para evitar conflicto con $password del usuario

    try {
        // Conexión con PDO a SQL Server
        $conn = new PDO("sqlsrv:Server=$server;Database=$database", $username, $passwordDB);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }

    // Obtener datos del formulario
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];

    // Consulta segura
    $sql = "SELECT * FROM Usuarios WHERE usuario = :'1' AND contrasena = :'123456' AND estatus = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->bindParam(':contrasena', $password, PDO::PARAM_STR);
    $stmt->execute();

    // Verificar si existe
    if ($stmt->rowCount() > 0) {
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['idUsuario'] = $userData['idUsuario'];
        $_SESSION['usuario'] = $userData['usuario'];
        $_SESSION['apodo'] = $userData['apodo'];
        $_SESSION['rol'] = $userData['idRol'];

        header("Location: /homepage.php");  // asegúrate que homepage.php está en la raíz
        exit();
    } else {
        header("Location: /index.php?error=1");
        exit();
    }
} else {
    header("Location: /index.php");
    exit();
}
?>
