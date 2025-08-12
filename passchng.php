<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Si no hay sesión activa, redirigir al login
    header("Location: index.php");
    exit();
}

// Variables para mensajes
$error = '';
$success = '';

// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $oldPass = $_POST['old_Pass'] ?? '';
    $newPass = $_POST['new_pass'] ?? '';
    $confirmPass = $_POST['confirm-pass'] ?? '';
    
    // Validar que las contraseñas no estén vacías
    if (empty($oldPass) || empty($newPass) || empty($confirmPass)) {
        $error = "Todos los campos son obligatorios";
    } 
    // Validar que las nuevas contraseñas coincidan
    elseif ($newPass !== $confirmPass) {
        $error = "Las nuevas contraseñas no coinciden";
    } 
    // Validar que la nueva contraseña sea diferente
    elseif ($oldPass === $newPass) {
        $error = "La nueva contraseña debe ser diferente a la actual";
    } 
    // Validar longitud mínima
    elseif (strlen($newPass) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres";
    } 
    // Si todo está bien, procesar el cambio
    else {
        // Conectar a la base de datos
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
            $error = "Error de conexión: " . print_r(sqlsrv_errors(), true);
        } else {
            // Obtener ID de usuario de la sesión
            $user_id = $_SESSION['user_id'];
            
            // Consulta para verificar contraseña actual
            $sql = "SELECT contrasena FROM usuarios WHERE idUsuario = ?";
            $params = array($user_id);
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt === false) {
                $error = "Error en la consulta: " . print_r(sqlsrv_errors(), true);
            } else {
                if (sqlsrv_has_rows($stmt)) {
                    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                    
                    // Verificar contraseña actual (en un sistema real debería ser con hash)
                    if ($oldPass === $row['contrasena']) {
                        // Actualizar contraseña (en un sistema real debería usar hash)
                        $updateSql = "UPDATE usuarios SET contrasena = ? WHERE idUsuario = ?";
                        $updateParams = array($newPass, $user_id);
                        $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);
                        
                        if ($updateStmt === false) {
                            $error = "Error al actualizar la contraseña: " . print_r(sqlsrv_errors(), true);
                        } else {
                            $success = "¡Contraseña actualizada correctamente!";
                        }
                    } else {
                        $error = "La contraseña actual es incorrecta";
                    }
                } else {
                    $error = "Usuario no encontrado";
                }
            }
            
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);
        }
    }
}

$rolActual   = (int)($_SESSION['rol'] ?? 0);
$notifTarget = ($rolActual === 1) ? 'admnrqst.php' : 'mis_notifs.php';

$unreadCount = 0;
$notifList   = [];

$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn) {
    if ($rolActual === 1) {
        // ADMIN: ver SOLO las destinadas a admin (idRol = 1)
        $stmtCount = sqlsrv_query($conn, "SELECT COUNT(*) AS c FROM Notificaciones WHERE solicitudRevisada = 0 AND idRol = 1");
        $stmtList  = sqlsrv_query($conn, "SELECT TOP 10 idNotificacion, descripcion, fecha
                                          FROM Notificaciones
                                          WHERE solicitudRevisada = 0 AND idRol = 1
                                          ORDER BY fecha DESC");
    } else {
        // USUARIO: ver SOLO las destinadas a su rol (p. ej. 2)
        $stmtCount = sqlsrv_query($conn, "SELECT COUNT(*) AS c FROM Notificaciones WHERE solicitudRevisada = 0 AND idRol = ?", [$rolActual]);
        $stmtList  = sqlsrv_query($conn, "SELECT TOP 10 idNotificacion, descripcion, fecha
                                          FROM Notificaciones
                                          WHERE solicitudRevisada = 0 AND idRol = ?
                                          ORDER BY fecha DESC", [$rolActual]);
    }

    if ($stmtCount) {
        $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
        $unreadCount = (int)($row['c'] ?? 0);
        sqlsrv_free_stmt($stmtCount);
    }

    if ($stmtList) {
        while ($r = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) {
            $notifList[] = $r;
        }
        sqlsrv_free_stmt($stmtList);
    }

    sqlsrv_close($conn);
}
?>


<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Password Change</title>
    <link rel="stylesheet" href="css/StylePSCH.css">
</head>

<body>
<header>
  <div class="brand">
    <img src="img/cmapa.png" class="logo" />
    <h1>SIA - CMAPA</h1>
  </div>

  <div class="header-right">
    <div class="notification-container">
      <button class="icon-btn" id="notif-toggle" type="button" aria-label="Notificaciones">
        <img
          src="<?= $unreadCount > 0 ? 'img/belldot.png' : 'img/bell.png' ?>"
          class="imgh3"
          alt="Notificaciones"
        />
      </button>

      <div class="notification-dropdown" id="notif-dropdown" style="display:none;">
        <?php if ($unreadCount === 0): ?>
          <div class="notif-empty" style="padding:10px;">No hay notificaciones nuevas.</div>
        <?php else: ?>
          <ul class="notif-list" style="list-style:none; margin:0; padding:0; max-height:260px; overflow:auto;">
            <?php foreach ($notifList as $n): ?>
              <li class="notif-item"
                  style="padding:8px 10px; cursor:pointer; border-bottom:1px solid #eaeaea;"
                  onclick="window.location.href='<?= $notifTarget ?>'">
                <div class="notif-desc" style="font-size:0.95rem;">
                  <?= htmlspecialchars($n['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="notif-date" style="font-size:0.8rem; opacity:0.7;">
                  <?php
                    $f = $n['fecha'];
                    if ($f instanceof DateTime) echo $f->format('Y-m-d H:i');
                    else { $dt = @date_create(is_string($f) ? $f : 'now'); echo $dt ? $dt->format('Y-m-d H:i') : ''; }
                  ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
          <div style="padding:8px 10px;">
            <button type="button" class="btn" onclick="window.location.href='<?= $notifTarget ?>'">Ver todas</button>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <p><?= htmlspecialchars($_SESSION['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

    <div class="user-menu-container">
      <button class="icon-btn" id="user-toggle" type="button">
        <img src="img/userB.png" class="imgh2" alt="Usuario" />
      </button>
      <div class="user-dropdown" id="user-dropdown" style="display:none;">
        <p><strong>Usuario:</strong> <?= (int)($_SESSION['rol'] ?? 0) ?></p>
        <p><strong>Apodo:</strong> <?= htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        <a href="passchng.php"><button class="user-option" type="button">CAMBIAR CONTRASEÑA</button></a>
      </div>
    </div>

    <div class="menu-container">
      <button class="icon-btn" id="menu-toggle" type="button">
        <img src="img/menu.png" alt="Menú" />
      </button>
      <div class="dropdown" id="dropdown-menu" style="display:none;">
        <a href="homepage.php">Inicio</a>
        <a href="mnthclsr.php">Cierre de mes</a>
        <a href="admin.php">Menu de administador</a>
        <a href="about.php">Acerca de</a>
        <a href="help.php">Ayuda</a>
        <a href="logout.php">Cerrar Sesion</a>
      </div>
    </div>
  </div>
</header>
<main class="pwd-container">
  <form id="form-pass" method="POST" action="passchng.php">
    <div class="pwd-box">
       <?php if (!empty($error)): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message"><?= $success ?></div>
        <?php endif; ?>
        <!-- Contraseña anterior -->
        <div class="pwd-field">
            <img src="img/padlock.png" class="pwd-icon" alt="Lock">
            <input type="password" id="old-pass" name="old_Pass" placeholder="Contraseña Anterior" required>
        </div>
        <!-- Contraseña nueva -->
        <div class="pwd-field">
            <img src="img/padlock.png" class="pwd-icon" alt="Lock">
            <input type="password" id="new-pass" name="new_pass" placeholder="Contraseña Nueva" required>
        </div>
        
        <!-- Confirmar contraseña -->
        <div class="pwd-field">
            <img src="img/padlock.png" class="pwd-icon" alt="Lock">
            <input type="password" id="confirm-pass" name="confirm-pass" placeholder="Confirmar Contraseña" required>
        </div>
        <!-- Botón aceptar -->
        <button type="submit" class="accept-btn" id="pwd-accept">ACEPTAR</button>
        <!-- Mensaje de resultado -->
         <div class="password-strength">
                <div class="strength-bar" id="strength-bar"></div>
            </div>
            <div class="strength-text" id="strength-text"></div>
        </div>
        <p id="mensaje-resultado" class="mensaje"></p>
    </div>
  </form>
</main>

<script>
  const toggle = document.getElementById('menu-toggle');
  const dropdown = document.getElementById('dropdown-menu');
  toggle.addEventListener('click', () => {
    dropdown.style.display = dropdown.style.display === 'flex' ? 'none' : 'flex';
  });
  window.addEventListener('click', (e) => {
    if (!toggle.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.style.display = 'none';
    }
  });
</script>

<script>
  const userToggle = document.getElementById('user-toggle');
  const userDropdown = document.getElementById('user-dropdown');
  userToggle.addEventListener('click', () => {
    userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
  });

  // Cerrar el menú al hacer clic fuera
  window.addEventListener('click', (e) => {
    if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) {
      userDropdown.style.display = 'none';
    }
  });
</script>

<script>
  const notifToggle = document.getElementById('notif-toggle');
  const notifDropdown = document.getElementById('notif-dropdown');
  notifToggle.addEventListener('click', () => {
    notifDropdown.style.display = notifDropdown.style.display === 'block' ? 'none' : 'block';
  });
  window.addEventListener('click', (e) => {
    if (!notifToggle.contains(e.target) && !notifDropdown.contains(e.target)) {
      notifDropdown.style.display = 'none';
    }
  });
</script>  
</body>

</html>
