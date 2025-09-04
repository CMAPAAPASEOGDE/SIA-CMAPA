<?php
session_start();
$error = '';

/* ==== Config DB común ==== */
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
  "Database" => "db_sia",
  "Uid"      => "cmapADMIN",
  "PWD"      => "@siaADMN56*",
  "Encrypt"  => true,
  "TrustServerCertificate" => false
];

/* ==== Logger seguro (con fallback) ==== */
$logFile = __DIR__ . '/php/log_utils.php';
if (file_exists($logFile)) {
  require_once $logFile;
  $conn = db_conn_or_die();    // usa la misma conexión para todo el script
  logs_boot($conn);            // limpieza de logs (>30 días) 1 vez/día
} else {
  // Fallback: conexión y funciones “no-op” para que nunca truene el index
  $conn = sqlsrv_connect($serverName, $connectionOptions);
  if ($conn === false) { die(print_r(sqlsrv_errors(), true)); }
  function log_event($c, $u, $a, $d, $m, $e = 1) { /* noop si no hay util */ }
}

/* ==== POST: intento de login ==== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $usuario    = trim($_POST['usuario'] ?? '');
  $contrasena = $_POST['contrasena'] ?? '';

  $sql = "SELECT TOP 1 idUsuario, usuario, idRol, apodo
            FROM dbo.usuarios
           WHERE usuario   COLLATE Latin1_General_CI_AS  = ?
             AND contrasena COLLATE Latin1_General_CS_AS = ?
             AND estatus = 1";
  $params = [$usuario, $contrasena];
  $stmt = sqlsrv_query($conn, $sql, $params);

  if ($stmt === false) {
    $error = "Error en la consulta.";
    log_event($conn, 0, 'LOGIN_QUERY_ERROR', 'SQL error en index.php', 'AUTH', 0);
  } else {
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($row) {
      // Sesión
      $_SESSION['user_id'] = (int)$row['idUsuario'];
      $_SESSION['nombre']  = (string)$row['apodo'];
      $_SESSION['rol']     = (int)$row['idRol'];
      $_SESSION['usuario'] = (string)$row['usuario'];

      // Log ANTES del redirect
      log_event($conn, (int)$row['idUsuario'], 'LOGIN_OK', 'Inicio de sesión: '.$row['usuario'], 'AUTH', 1);

      header("Location: homepage.php");
      exit();
    } else {
      $error = "Credenciales inválidas o cuenta inactiva, si el problema persiste contacte al administrador del sistema";
      log_event($conn, 0, 'LOGIN_FAIL', 'Usuario='.$usuario, 'AUTH', 0);
    }
    sqlsrv_free_stmt($stmt);
  }
}

// Cierra conexión (opcional)
if (is_resource($conn)) { sqlsrv_close($conn); }
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <title>SIA Login</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .auth-error{
      max-width:520px;margin:16px auto 0;background:#ffe8e8;border:1px solid #e57373;
      color:#b00020;padding:12px 14px;border-radius:8px;text-align:center;
      box-shadow:0 2px 6px rgba(0,0,0,.05);font-size:14px;
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
    <div class="auth-error" role="alert"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
</body>
</html>
