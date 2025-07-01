<?php
session_start();

// Configuración de conexión a tu MySQL en Azure
$serverName = "dbsia-inventory.mysql.database.azure.com"; // sin tcp: ni puerto
$database = "dbsiacmapa";
$username = "SIAdmin";
$password = "serVEr*56.sQL#21$";

// Recibe usuario y contraseña del formulario
$user = $_POST['user'];
$pass = $_POST['password'];

// Conexión con MySQL usando PDO
try {
    $conn = new PDO("mysql:host=$serverName;dbname=$database;charset=utf8", $username, $password, [
        PDO::MYSQL_ATTR_SSL_CA => "../certs/DigiCertGlobalRootG2.crt.pem", // AJUSTA ESTA RUTA
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Consulta para verificar usuario
    $sql = "SELECT * FROM usuarios WHERE idUsuario = :usuario AND contrasena = :contrasena";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':usuario', $user);
    $stmt->bindParam(':contrasena', $pass);
    $stmt->execute();

    if ($stmt->rowCount() == 1) {
        $_SESSION['usuario'] = $user;
        header("Location: ../homepage.php");
        exit();
    } else {
        echo "<script>alert('Usuario o contraseña incorrectos'); window.location.href='../index.php';</script>";
    }

} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
}
?>
