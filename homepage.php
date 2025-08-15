<?php
session_start();

// Verificar sesión
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$rolActual   = (int)($_SESSION['rol'] ?? 0);
// Admin -> admnrqst.php | Usuario -> inventory.php
$notifTarget = ($rolActual === 1) ? 'admnrqst.php' : 'inventory.php';

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
        // ADMIN: pendientes para admin
        $stmtCount = sqlsrv_query($conn,
            "SELECT COUNT(*) AS c
             FROM Notificaciones
             WHERE idRol = 1 AND solicitudRevisada = 0");
        $stmtList  = sqlsrv_query($conn,
            "SELECT TOP 10 idNotificacion, descripcion, fecha
             FROM Notificaciones
             WHERE idRol = 1 AND solicitudRevisada = 0
             ORDER BY fecha DESC");
    } else {
        // USUARIO: ver PENDIENTES para usuarios (no leídas por el usuario)
          $stmtCount = sqlsrv_query($conn,
              "SELECT COUNT(*) AS c
              FROM Notificaciones
              WHERE idRol = 2 AND solicitudRevisada = 0");
          $stmtList  = sqlsrv_query($conn,
              "SELECT TOP 10 idNotificacion, descripcion, fecha
              FROM Notificaciones
              WHERE idRol = 2 AND solicitudRevisada = 0
              ORDER BY fecha DESC");
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
    <title>SIA Homepage</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styleHP.css">
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
              <?php
                $idNoti = (int)($n['idNotificacion'] ?? 0);
                $f = $n['fecha'];
                $fechaTxt = ($f instanceof DateTime) ? $f->format('Y-m-d H:i')
                           : (($dt = @date_create(is_string($f) ? $f : 'now')) ? $dt->format('Y-m-d H:i') : '');
              ?>
              <li class="notif-item"
                  style="padding:8px 10px; cursor:pointer; border-bottom:1px solid #eaeaea;"
                  <?php if ($rolActual === 2): ?>
                    onclick="ackUserNotif(<?= $idNoti ?>)"
                  <?php else: ?>
                    onclick="window.location.href='<?= $notifTarget ?>'"
                  <?php endif; ?>>
                <div class="notif-desc" style="font-size:0.95rem;">
                  <?= htmlspecialchars($n['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="notif-date" style="font-size:0.8rem; opacity:0.7;"><?= $fechaTxt ?></div>
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

<main class="menu">
  <div class="card">
    <img src="img/warehouse.png" alt="Almacén" />
    <a href="warehouse.php"><button class="card-btn">MI ALMACEN</button></a>
  </div>
  <div class="card">
    <img src="img/inventory-management.png" alt="Inventario" />
    <a href="inventory.php"><button class="card-btn">INVENTARIO</button></a>
  </div>
  <div class="card">
    <img src="img/documentation.png" alt="Reportes" />
    <a href="reports.php"><button class="card-btn">REPORTES</button></a>
  </div>
</main>

<script>
  // Menú hamburguesa
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

  // Menú de usuario
  const userToggle = document.getElementById('user-toggle');
  const userDropdown = document.getElementById('user-dropdown');
  userToggle.addEventListener('click', () => {
    userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
  });
  window.addEventListener('click', (e) => {
    if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) {
      userDropdown.style.display = 'none';
    }
  });

  // Notificaciones
  const notifToggle = document.getElementById('notif-toggle');
  const notifDropdown = document.getElementById('notif-dropdown');
  if (notifToggle && notifDropdown) {
    notifToggle.addEventListener('click', () => {
      notifDropdown.style.display = (notifDropdown.style.display === 'block') ? 'none' : 'block';
    });
    window.addEventListener('click', (e) => {
      if (!notifToggle.contains(e.target) && !notifDropdown.contains(e.target)) {
        notifDropdown.style.display = 'none';
      }
    });
  }

  // Solo usuario (rol 2): marcar como vista y redirigir
  function ackUserNotif(idNotificacion) {
    fetch('php/ack_notif.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
      body: 'id=' + encodeURIComponent(idNotificacion)
    })
    .then(r => r.json()).catch(() => ({}))
    .finally(() => { window.location.href = 'inventory.php'; });
  }
</script>
</body>
</html>
