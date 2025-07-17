<?php
session_start();

// Verificar permisos
if (!isset($_SESSION['user_id']) || (int)$_SESSION['rol'] !== 1) {
    header("Location: ../index.php");
    exit();
}

// Obtener el usuario del formulario
$usuarioBuscar = $_POST['usuario'] ?? '';

if (empty($usuarioBuscar)) {
    $_SESSION['error_busqueda'] = "Debe ingresar un nombre de usuario";
    header("Location: ../admnusredsrch.php");
    exit();
}

// Conexión a la base de datos
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = array(
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    $_SESSION['error_busqueda'] = "Error de conexión: " . print_r(sqlsrv_errors(), true);
    header("Location: ../admnusredsrcher.php");
    exit();
}

// Buscar el usuario
$sql = "SELECT idUsuario, usuario, apodo, idRol, estatus 
        FROM usuarios 
        WHERE usuario = ?";
$params = array($usuarioBuscar);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $_SESSION['error_busqueda'] = "Error en la consulta: " . print_r(sqlsrv_errors(), true);
    header("Location: ../admnusredsrcher.php");
    exit();
}

if (sqlsrv_has_rows($stmt)) {
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $_SESSION['editar_usuario'] = $row;
    header("Location: ../admnusredt.php");
    exit();
} else {
    $_SESSION['error_busqueda'] = "Usuario no encontrado: $usuarioBuscar";
    header("Location: ../admnusredsrcher.php");
    exit();
}
?>