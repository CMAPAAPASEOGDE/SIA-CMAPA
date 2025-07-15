<?php
session_start();
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];
    
    // Conexión a Azure SQL Server
    $serverName = "sqlserver-sia.database.windows.net";
    $connectionOptions = array(
        "Database" => "db_sia",
        "Uid" => "cmapADMIN",
        "PWD" => "@siaADMN56*"
    );
    
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    
    if ($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    
    // Consulta segura con parámetros
    $sql = "SELECT idUsuario, usuario, idRol FROM usuarios WHERE usuario = ? AND contrasena = ?";
    $params = array($usuario, $contrasena);
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        $error = "Error en la consulta: " . print_r(sqlsrv_errors(), true);
    } else {
        if (sqlsrv_has_rows($stmt)) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            
            // Crear sesión
            $_SESSION['user_id'] = $row['idUsuario'];
            $_SESSION['nombre'] = $row['usuario'];
            $_SESSION['rol'] = $row['idRol'];
            
            // Redirección
            header("Location: homepage.php");
            exit();
        } else {
            $error = "Credenciales inválidas";
        }
    }
    
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
}
?>

<!DOCTYPE html>

<html>

<head>
  <meta charset="UTF-8" />
  <title>SIA Login</title>
  <link rel="stylesheet" href="css/style.css">
</head>

<body>

<?php if (!empty($error)): ?>
        <div style="color:red;"><?= $error ?></div>
    <?php endif; ?>

  <header>
    <img src="img/cmapa.png" />
    <h1>Sistema de Inventario de Almacén - CMAPA</h1>
    <p>Versión 1.2</p>
  </header>

  <section class="container">
    <h2>Bienvenid@</h2>
    <form method="POST">
      <div>
        <img src="img/userB.png" />
        <input type="text" name="usuario" placeholder="Usuario" required />
      </div>
      <div>
        <img src="img/padlockB.png" />
        <input type="password" name="contrasena" placeholder="Contraseña" required />
      </div>
      <p class="forgot">
        <a href="#" onclick="mostrarMensaje()">Olvidé mi contraseña</a>
      </p>
      <button type="submit">Iniciar Sesión</button>
    </form>
    <p id="mensaje-info" style="display: none;">
      PARA UN CAMBIO DE USUARIO Y/O CONTRASEÑA ES NECESARIO QUE SE PONGA EN CONTACTO CON EL ADMINISTRADOR DEL SISTEMA.
    </p>
  </section>

  <script>
    function mostrarMensaje() {
      document.getElementById('mensaje-info').style.display = 'block';
    }
  </script>
</body>
</html>
