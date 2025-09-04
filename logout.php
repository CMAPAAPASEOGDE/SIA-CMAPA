<?php
session_start();

require_once __DIR__.'/php/log_utils.php';
$conn = db_conn_or_die(); logs_boot($conn);
log_event($conn, (int)($_SESSION['user_id'] ?? 0), 'LOGOUT', 'Cierre de sesión', 'AUTH', 1);

// Destruir todas las variables de sesión
$_SESSION = array();

// Eliminar la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redireccionar con parámetros para evitar caché
header("Location: index.php?logout=1");
exit();
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA LOGOUT</title>
    <link rel="stylesheet" href="css/StyleLOGOUT.css">
</head>

<body>
  <header>
    <img src="img/cmapa.png" />
    <h1>Sistema de Inventario de Almacén - <strong>CMAPA</strong></h1>
    <p>Versión 1.2</p>
  </header>

  <section class="container">
    <h2>NOS VEMOS PRONTO</h2>
    <form action="index.php">
      <button type="submit">VOLVER AL INICIO DE SESION</button>
    </form>
  </section>
</body>

</html>