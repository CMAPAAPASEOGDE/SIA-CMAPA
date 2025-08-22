<?php
// Iniciar sesión
session_start();

// Autenticación
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php"); exit();
}
// Solo admin
$idRol = (int)($_SESSION['rol'] ?? 0);
if ($idRol !== 1) {
    header("Location: acceso_denegado.php"); exit();
}

// Conexión SQL Server
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
    die("Error de conexión: " . print_r(sqlsrv_errors(), true));
}

/* ======================================================
   1) Confirmar solicitud: Modificaciones -> solicitudRevisada=1
      y crear Notificaciones (idModificacion, comentario admin)
   ====================================================== */
$flashMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'confirmar') {
    $idModificacion = (int)($_POST['idModificacion'] ?? 0);
    $comentario     = trim($_POST['comentario'] ?? '');

    if ($idModificacion > 0) {
        if (sqlsrv_begin_transaction($conn) === false) {
            $flashMsg = "No se pudo iniciar la transacción.";
        } else {
            $ok1 = sqlsrv_query(
                $conn,
                "UPDATE Modificaciones SET solicitudRevisada = 1 WHERE idModificacion = ?",
                [$idModificacion]
            );

            $ok2 = false;
            if ($ok1) {
                $ok2 = sqlsrv_query(
                    $conn,
                    "INSERT INTO Notificaciones (idModificacion, descripcion, fechaNotificacion, estatusRevision)
                     VALUES (?, ?, SYSDATETIME(), 0)",
                    [$idModificacion, $comentario]
                );
            }

            if ($ok1 && $ok2) {
                sqlsrv_commit($conn);
                $flashMsg = "Solicitud #$idModificacion confirmada y notificada al usuario.";
            } else {
                sqlsrv_rollback($conn);
                $flashMsg = "No se pudo confirmar la solicitud #$idModificacion.";
            }
        }
    } else {
        $flashMsg = "Solicitud inválida.";
    }
}

/* ======================================================
   2) Notificaciones del header (ADMIN): vienen de Modificaciones
   ====================================================== */
$unreadCount = 0;
$notifList   = [];

$stmtCount = sqlsrv_query(
    $conn,
    "SELECT COUNT(*) AS c
       FROM Modificaciones
      WHERE idRol = 1 AND solicitudRevisada = 0"
);
if ($stmtCount) {
    $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
    $unreadCount = (int)($row['c'] ?? 0);
    sqlsrv_free_stmt($stmtCount);
}

$stmtList = sqlsrv_query(
    $conn,
    "SELECT TOP 10 idModificacion, descripcion, fecha, tipo, cantidad
       FROM Modificaciones
      WHERE idRol = 1 AND solicitudRevisada = 0
      ORDER BY fecha DESC"
);
if ($stmtList) {
    while ($r = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) {
        $notifList[] = $r;
    }
    sqlsrv_free_stmt($stmtList);
}

/* ======================================================
   3) Tablero principal: solicitudes pendientes (Modificaciones)
   ====================================================== */
$solicitudes = [];
$sqlPend = "
    SELECT M.idModificacion, M.tipo, M.descripcion, M.cantidad, M.fecha,
           M.idCodigo, P.codigo AS codigoProducto
      FROM Modificaciones M
 LEFT JOIN Productos P ON P.idCodigo = M.idCodigo
     WHERE M.idRol = 1 AND M.solicitudRevisada = 0
  ORDER BY M.fecha DESC";
$stmtPend = sqlsrv_query($conn, $sqlPend);
if ($stmtPend) {
    while ($s = sqlsrv_fetch_array($stmtPend, SQLSRV_FETCH_ASSOC)) {
        $solicitudes[] = $s;
    }
    sqlsrv_free_stmt($stmtPend);
}

sqlsrv_close($conn);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Administrator</title>
    <link rel="stylesheet" href="css/StyleADRQST.css">
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
        <?php else: ?>
          <ul class="notif-list" style="list-style:none; margin:0; padding:0; max-height:260px; overflow:auto;">
            <?php foreach ($notifList as $n): ?>
              <?php
                $f = $n['fecha'] ?? null;
                $fechaTxt = ($f instanceof DateTime)
                  ? $f->format('Y-m-d H:i')
                  : (($dt = @date_create(is_string($f) ? $f : 'now')) ? $dt->format('Y-m-d H:i') : '');
                $tipoTxt = strtoupper((string)($n['tipo'] ?? ''));
                $qtyTxt  = isset($n['cantidad']) ? ' • Cant.: '.(int)$n['cantidad'] : '';
              ?>
              <li class="notif-item"
                  style="padding:8px 10px; cursor:pointer; border-bottom:1px solid #eaeaea;"
                  onclick="window.location.href='admnrqst.php'">
                <div class="notif-desc" style="font-size:0.95rem;">
                  [<?= htmlspecialchars($tipoTxt, ENT_QUOTES, 'UTF-8') ?>]
                  <?= htmlspecialchars($n['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') . $qtyTxt ?>
                </div>
                <div class="notif-date" style="font-size:0.8rem; opacity:0.7;"><?= $fechaTxt ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
          <div style="padding:8px 10px;">
            <button type="button" class="btn" onclick="window.location.href='admnrqst.php'">Ver todas</button>
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
        <p><strong>Tipo de usuario:</strong> <?= (int)($_SESSION['rol'] ?? 0) ?></p>
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

<main class="rqst-container">
  <div class="rqst-title">MODIFICACIONES DE ALMACEN</div>

  <?php if ($flashMsg): ?>
    <div class="rqst-flash"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <section class="rqst-board">
    <?php if (empty($solicitudes)): ?>
      <div class="rqst-empty">No hay solicitudes pendientes.</div>
    <?php else: ?>
      <?php foreach ($solicitudes as $s): ?>
        <form method="POST" class="rqst-row">
          <div class="rqst-type">
            <?= htmlspecialchars(strtoupper($s['tipo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          </div>

          <div class="rqst-desc">
            <?php if (!empty($s['codigoProducto'])): ?>
              <div class="rqst-code"><?= htmlspecialchars($s['codigoProducto'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div class="rqst-text"><?= htmlspecialchars($s['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
          </div>

          <div class="rqst-qty">
            <?= isset($s['cantidad']) && $s['cantidad'] !== null ? (int)$s['cantidad'] : '—' ?>
          </div>

          <div class="rqst-comment">
            <textarea name="comentario" rows="2" placeholder="Comentario del administrador (opcional)"></textarea>
          </div>

          <div class="rqst-actions">
            <input type="hidden" name="idModificacion" value="<?= (int)$s['idModificacion'] ?>">
            <button type="submit" name="accion" value="confirmar" class="rqst-confirm">
              CONFIRMAR
            </button>
          </div>
        </form>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <div class="rqst-bottom">
    <a href="admin.php"><button type="button" class="btn-cancel">CANCELAR</button></a>
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
    if (!toggle.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
  });

  // Menú usuario
  const userToggle = document.getElementById('user-toggle');
  const userDropdown = document.getElementById('user-dropdown');
  userToggle.addEventListener('click', () => {
    userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
  });
  window.addEventListener('click', (e) => {
    if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) userDropdown.style.display = 'none';
  });

  // Notificaciones header
  const notifToggle = document.getElementById('notif-toggle');
  const notifDropdown = document.getElementById('notif-dropdown');
  notifToggle.addEventListener('click', () => {
    notifDropdown.style.display = notifDropdown.style.display === 'block' ? 'none' : 'block';
  });
  window.addEventListener('click', (e) => {
    if (!notifToggle.contains(e.target) && !notifDropdown.contains(e.target)) notifDropdown.style.display = 'none';
  });
</script>
</body>
</html>
