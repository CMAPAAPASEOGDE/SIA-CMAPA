<?php
session_start();

// Verificar permisos
if (!isset($_SESSION['user_id']) || (int)$_SESSION['rol'] !== 1) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos del formulario
    $idUsuario = (int)$_POST['idUsuario'];
    $usuario = $_POST['usuario'];
    $rol = (int)$_POST['rol'];
    $apodo = $_POST['apodo'];
    $estatus = (int)$_POST['estatus'];
    $cambiarContra = isset($_POST['cambiar_contra']) && $_POST['cambiar_contra'] == '1';

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
        $_SESSION['error_actualizacion'] = "Error de conexión: " . print_r(sqlsrv_errors(), true);
        header("Location: ../admnusredt.php");
        exit();
    }

    try {
        // Si se quiere cambiar la contraseña
        if ($cambiarContra) {
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (empty($password)) {
                $_SESSION['error_actualizacion'] = "Debe ingresar una nueva contraseña";
                header("Location: ../admnusredt.php");
                exit();
            }

            if ($password !== $confirm) {
                $_SESSION['error_actualizacion'] = "Las contraseñas no coinciden";
                header("Location: ../admnusredt.php");
                exit();
            }

            // Guardar la contraseña como texto plano
            $sql = "UPDATE usuarios 
                    SET apodo = ?, idRol = ?, estatus = ?, contrasena = ?, contrasena_sal = NULL
                    WHERE idUsuario = ?";
            $params = array($apodo, $rol, $estatus, $password, $idUsuario);
        } else {
            $sql = "UPDATE usuarios 
                    SET apodo = ?, idRol = ?, estatus = ?
                    WHERE idUsuario = ?";
            $params = array($apodo, $rol, $estatus, $idUsuario);
        }

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $_SESSION['error_actualizacion'] = "Error al actualizar: " . $errors[0]['message'];
            header("Location: ../admnusredt.php");
            exit();
        }

        // Actualizar datos de sesión si es el mismo usuario
        if ($_SESSION['user_id'] == $idUsuario) {
            $_SESSION['nombre'] = $apodo;
            $_SESSION['rol'] = $rol;
        }

        unset($_SESSION['editar_usuario']);
        header("Location: ../admnusredtcf.php");
        exit();

    } finally {
        sqlsrv_close($conn);
    }

} else {
    header("Location: ../admnusredtcf.php");
    exit();
}
?>
