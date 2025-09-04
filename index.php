<?php
session_start();
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario    = $_POST['usuario'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';
    
    // Conexión a Azure SQL Server
    $serverName = "sqlserver-sia.database.windows.net";
    $connectionOptions = array(
        "Database" => "db_sia",
        "Uid" => "cmapADMIN",
        "PWD" => "@siaADMN56*",
        "Encrypt" => true,
        "TrustServerCertificate" => false
    );
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if ($conn === false) { die(print_r(sqlsrv_errors(), true)); }

    require_once __DIR__.'/php/log_utils.php';
    $conn = db_conn_or_die();
    logs_boot($conn); // retención 30 días (1 vez/día)

    // Consulta con parámetros
    $sql = "SELECT idUsuario, usuario, idRol, apodo
            FROM dbo.usuarios
            WHERE usuario COLLATE Latin1_General_CI_AS = ?
              AND contrasena COLLATE Latin1_General_CS_AS = ?
              AND estatus = 1";
    $params = array($usuario, $contrasena);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $error = "Error en la consulta.";
    } else {
        if (sqlsrv_has_rows($stmt)) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            // Crear sesión
            $_SESSION['user_id'] = $row['idUsuario'];
            $_SESSION['nombre']  = $row['apodo'];
            $_SESSION['rol']     = (int)$row['idRol'];
            $_SESSION['usuario'] = $row['usuario'];
            header("Location: homepage.php");
            exit();
            log_event($conn, (int)$row['idUsuario'], 'LOGIN_OK', 'Inicio de sesión correcto: '.$row['usuario'], 'AUTH', 1);
        } else {
            $error = "Credenciales inválidas o cuenta inactiva, si el problema persiste contacte al administrador del sistema";
            log_event($conn, 0, 'LOGIN_FAIL', 'Fallo de login usuario='.$usuario, 'AUTH', 0);
        }
    }
    if ($stmt) { sqlsrv_free_stmt($stmt); }
    sqlsrv_close($conn);
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <title>SIA Login</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* Caja de error centrada debajo de la tarjeta */
    .auth-error {
      max-width: 520px; 
      margin: 16px auto 0; 
      background: #ffe8e8;
      border: 1px solid #e57373;
      color: #b00020;
      padding: 12px 14px; 
      border-radius: 8px; 
      text-align: center;
      box-shadow: 0 2px 6px rgba(0,0,0,.05);
      font-size: 14px;
    }
  </style>
</head>
<body>
  <header>
    <img src="img/cmapa.png" />
    <h1>Sistema de Inventario de Almacén - CMAPA</h1>
    <p>Versión 1.3.1</p>
  </header>

  <section class="container">
    <h2>Bienvenid@</h2>
    <form method="POST" autocomplete="off">
      <div>
        <img src="img/userB.png" />
        <input type="text" name="usuario" placeholder="Usuario" required
               value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" />
      </div>
      <div>
        <img src="img/padlockB.png" />
        <input type="password" name="contrasena" placeholder="Contraseña" required />
      </div>
      <button type="submit">Iniciar Sesión</button>
    </form>
  </section>

  <?php if (!empty($error)): ?>
    <!-- Este bloque aparece justo en el “recuadro verde” (debajo del login) -->
    <div class="auth-error" role="alert">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <script>
    function mostrarMensaje() {
      const el = document.getElementById('mensaje-info');
      if (el) el.style.display = 'block';
    }
  </script>
</body>
</html>
