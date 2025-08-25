<?php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1, 2], true)) {
    header("Location: acceso_denegado.php");
    exit();
}

// Mensajes flash
if (isset($_SESSION['success'])) {
    echo '<div class="success-message">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="error-message">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}
if (isset($_GET['msg']) && $_GET['msg'] === 'caja_eliminada') {
    echo '<div class="success-message">¡Caja eliminada correctamente! Los productos han sido devueltos al inventario.</div>';
}

// Conexión SQL Server (una sola vez)
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

/* ================================
   NOTIFICACIONES (header unificado)
   ================================ */
$rolActual   = (int)($_SESSION['rol'] ?? 0);
$unreadCount = 0;
$notifList   = [];
$notifTarget = ($rolActual === 1) ? 'admnrqst.php' : 'boxes.php';

// Admin: desde Modificaciones (pendientes)
if ($rolActual === 1) {
    $stmtCount = sqlsrv_query($conn,
        "SELECT COUNT(*) AS c
           FROM Modificaciones
          WHERE solicitudRevisada = 0");
    $stmtList  = sqlsrv_query($conn,
        "SELECT TOP 10
                M.idModificacion,
                M.descripcion,
                M.fechaSolicitud,
                M.tipo,
                M.cantidad,
                P.codigo AS codigoProducto
           FROM Modificaciones M
      LEFT JOIN Productos P ON P.idCodigo = M.idCodigo
          WHERE M.solicitudRevisada = 0
       ORDER BY M.fechaSolicitud DESC");
}
// Usuario (rol 2): desde Notificaciones (no leídas)
else {
    $stmtCount = sqlsrv_query($conn,
        "SELECT COUNT(*) AS c
           FROM Notificaciones
          WHERE estatusRevision = 0");
    $stmtList  = sqlsrv_query($conn,
        "SELECT TOP 10
                N.idNotificacion,
                N.descripcion      AS comentarioAdmin,
                N.fechaNotificacion,
                P.codigo           AS codigoProducto
           FROM Notificaciones N
      LEFT JOIN Modificaciones M ON M.idModificacion = N.idModificacion
      LEFT JOIN Productos      P ON P.idCodigo       = M.idCodigo
          WHERE N.estatusRevision = 0
       ORDER BY N.fechaNotificacion DESC");
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

/* ================================
   Consulta de Cajas
   ================================ */
$sql = "SELECT C.numeroCaja, O.nombreCompleto AS nombreOperador, C.idCaja
        FROM CajaRegistro C
        INNER JOIN Operativo O ON C.idOperador = O.idOperador
        WHERE C.numeroCaja <> '0000'";
$result = sqlsrv_query($conn, $sql);
if ($result === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Toolbox</title>
    <link rel="stylesheet" href="css/StyleBX.css">
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
        <img src="<?= $unreadCount > 0 ? 'img/belldot.png' : 'img/bell.png' ?>" class="imgh3" alt="Notificaciones" />
      </button>

      <div class="notification-dropdown" id="notif-dropdown" style="display:none;">
        <?php if ($unreadCount === 0): ?>
          <div class="notif-empty" style="padding:10px;">No hay notificaciones nuevas.</div>

        <?php elseif ($rolActual === 1): ?>
          <ul class="notif-list" style="list-style:none; margin:0; padding:0; max-height:260px; overflow:auto;">
            <?php foreach ($notifList as $n):
              $f = $n['fechaSolicitud'] ?? null;
              $fechaTxt = ($f instanceof DateTime)
                            ? $f->format('Y-m-d H:i')
                            : (($dt = @date_create(is_string($f) ? $f : 'now')) ? $dt->format('Y-m-d H:i') : '');
              $tipoTxt = strtoupper((string)($n['tipo'] ?? ''));
              $qtyTxt  = isset($n['cantidad']) ? ' • Cant.: '.(int)$n['cantidad'] : '';
              $codigo  = (string)($n['codigoProducto'] ?? '');
            ?>
              <li class="notif-item"
                  style="padding:8px 10px; cursor:pointer; border-bottom:1px solid #eaeaea;"
                  onclick="window.location.href='admnrqst.php'">
                <div class="notif-desc" style="font-size:0.95rem;">
                  [<?= htmlspecialchars($tipoTxt, ENT_QUOTES, 'UTF-8') ?>]
                  <strong><?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?></strong><?= $qtyTxt ?> —
                  <?= htmlspecialchars($n['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="notif-date" style="font-size:0.8rem; opacity:0.7;"><?= $fechaTxt ?></div>
              </li>
            <?php endforeach; ?>
          </ul>

        <?php else: ?>
          <ul class="notif-list" style="list-style:none; margin:0; padding:0; max-height:260px; overflow:auto;">
            <?php foreach ($notifList as $n):
              $idNoti   = (int)($n['idNotificacion'] ?? 0);
              $codigo   = (string)($n['codigoProducto'] ?? '');
              $coment   = (string)($n['comentarioAdmin'] ?? '');
              $f        = $n['fechaNotificacion'] ?? null;
              $fechaTxt = ($f instanceof DateTime)
                            ? $f->format('Y-m-d H:i')
                            : (($dt = @date_create(is_string($f) ? $f : 'now')) ? $dt->format('Y-m-d H:i') : '');
            ?>
              <li class="notif-item"
                  style="padding:8px 10px; cursor:pointer; border-bottom:1px solid #eaeaea;"
                  onclick="ackUserNotif(<?= $idNoti ?>)">
                <div class="notif-desc" style="font-size:0.95rem;">
                  <strong><?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?></strong> —
                  <?= htmlspecialchars($coment, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="notif-date" style="font-size:0.8rem; opacity:0.7;"><?= $fechaTxt ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
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

<main class="cajas-container">
    <h2 class="cajas-title">CAJAS</h2>
    <section class="cajas-scroll">
        <?php while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)): ?>
            <div class="caja-card">
                <img src="img/caja-icono.png" alt="Caja <?= htmlspecialchars($row['numeroCaja']) ?>" class="caja-img" />
                <p class="caja-clave"><?= htmlspecialchars($row['nombreOperador']) ?></p>
                <a href="boxinspect.php?idCaja=<?= (int)$row['idCaja'] ?>"><button class="caja-bttn">CAJA <?= htmlspecialchars($row['numeroCaja']) ?></button></a>
            </div>
        <?php endwhile; ?>
    </section>
    <div class="cajas-actions">
        <a href="warehouse.php"><button class="btn cancel">CANCELAR</button></a>
        <a href="boxnewregister.php"><button class="btn confirm">REGISTRAR NUEVA CAJA</button></a>
    </div>
</main>

<script>
  // Menú hamburguesa
  const toggle = document.getElementById('menu-toggle');
  const dropdown = document.getElementById('dropdown-menu');
  if (toggle && dropdown) {
    toggle.addEventListener('click', () => {
      dropdown.style.display = dropdown.style.display === 'flex' ? 'none' : 'flex';
    });
    window.addEventListener('click', (e) => {
      if (!toggle.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });
  }

  // Menú de usuario
  const userToggle = document.getElementById('user-toggle');
  const userDropdown = document.getElementById('user-dropdown');
  if (userToggle && userDropdown) {
    userToggle.addEventListener('click', () => {
      userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
    });
    window.addEventListener('click', (e) => {
      if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) {
        userDropdown.style.display = 'none';
      }
    });
  }

  // Notificaciones: toggle dropdown
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

  // Confirmar lectura (rol 2)
  function ackUserNotif(idNotificacion) {
    fetch('php/ack_user_notif.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
      body: 'id=' + encodeURIComponent(idNotificacion)
    }).then(r => r.json()).catch(() => ({}))
      .finally(() => { window.location.href = 'boxes.php'; });
  }
</script>
</body>
</html>
