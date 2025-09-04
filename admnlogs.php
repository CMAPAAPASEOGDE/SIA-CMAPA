<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Si no hay sesión activa, redirigir al login
    header("Location: index.php");
    exit();
}

// Verificar el rol del usuario
$idRol = (int)($_SESSION['rol'] ?? 0);
if ($idRol !== 1) {
    header("Location: acceso_denegado.php");
    exit();
}

// -------- Notificaciones (nuevo esquema) --------
$rolActual   = (int)($_SESSION['rol'] ?? 0);
$unreadCount = 0;
$notifList   = [];

if (in_array($rolActual, [1,2,3], true)) {
    $serverName = "sqlserver-sia.database.windows.net";
    $connectionOptions = [
        "Database" => "db_sia",
        "Uid"      => "cmapADMIN",
        "PWD"      => "@siaADMN56*",
        "Encrypt"  => true,
        "TrustServerCertificate" => false
    ];
    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if ($conn) {
        if ($rolActual === 1) {
            // ADMIN: pendientes en Modificaciones
            $stmtCount = sqlsrv_query(
                $conn,
                "SELECT COUNT(*) AS c
                   FROM Modificaciones
                  WHERE solicitudRevisada = 0"
            );
            $stmtList = sqlsrv_query(
                $conn,
                "SELECT TOP 10
                        M.idModificacion,
                        M.descripcion,
                        M.fecha,
                        M.tipo,
                        M.cantidad,
                        P.codigo AS codigoProducto
                   FROM Modificaciones M
              LEFT JOIN Productos P ON P.idCodigo = M.idCodigo
                  WHERE M.solicitudRevisada = 0
               ORDER BY M.fecha DESC"
            );
        } else {
            // USUARIOS (2 y 3): avisos desde Notificaciones
            $stmtCount = sqlsrv_query(
                $conn,
                "SELECT COUNT(*) AS c
                   FROM Notificaciones
                  WHERE estatusRevision = 0"
            );
            $stmtList = sqlsrv_query(
                $conn,
                "SELECT TOP 10
                        N.idNotificacion,
                        N.descripcion      AS comentarioAdmin,
                        N.fechaNotificacion,
                        P.codigo           AS codigoProducto
                   FROM Notificaciones N
              LEFT JOIN Modificaciones M ON M.idModificacion = N.idModificacion
              LEFT JOIN Productos      P ON P.idCodigo       = M.idCodigo
                  WHERE N.estatusRevision = 0
               ORDER BY N.fechaNotificacion DESC"
            );
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
}

require_once __DIR__.'/php/log_utils.php';
$conn = db_conn_or_die(); logs_boot($conn);

$modulos = ['','AUTH','ALMACEN','CAJAS','REPORTES','CIERRE','SISTEMA'];

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-7 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');           // inclusive; en SQL sumo +1 día
$mod   = $_GET['modulo'] ?? '';
$acc   = $_GET['accion'] ?? '';
$est   = isset($_GET['estado']) ? (int)$_GET['estado'] : -1;  // -1 = todos
$q     = trim($_GET['q'] ?? '');
$page  = max(1, (int)($_GET['page'] ?? 1));
$per   = 50;
$off   = ($page - 1) * $per;

$sqlBase = "
  SELECT L.idLog, L.accion, L.descripcion, L.modulo, L.fechaHora, L.ipUsuario, L.estado,
         U.usuario, U.apodo
    FROM dbo.Logs L
    LEFT JOIN dbo.usuarios U ON U.idUsuario = L.idUsuario
   WHERE L.fechaHora >= ? AND L.fechaHora < DATEADD(day, 1, ?)";
$params = [$desde, $hasta];

if ($mod !== '') { $sqlBase .= " AND L.modulo = ?"; $params[] = $mod; }
if ($acc !== '') { $sqlBase .= " AND L.accion = ?"; $params[] = $acc; }
if ($est === 0 || $est === 1) { $sqlBase .= " AND L.estado = ?"; $params[] = $est; }
if ($q !== '') {
  $sqlBase .= " AND (L.descripcion LIKE ? OR U.usuario LIKE ? OR U.apodo LIKE ?)";
  $like = '%'.$q.'%'; array_push($params, $like, $like, $like);
}

$sqlCount = "SELECT COUNT(*) AS c FROM ($sqlBase) T";
$total = 0;
if ($stmt = sqlsrv_query($conn, $sqlCount, $params)) {
  $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
  $total = (int)($row['c'] ?? 0);
  sqlsrv_free_stmt($stmt);
}

$sql = $sqlBase." ORDER BY L.fechaHora DESC OFFSET $off ROWS FETCH NEXT $per ROWS ONLY";
$rows = [];
if ($stmt = sqlsrv_query($conn, $sql, $params)) {
  while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if ($r['fechaHora'] instanceof DateTime) {
      $r['fechaHora'] = $r['fechaHora']->format('Y-m-d H:i:s');
    }
    $rows[] = $r;
  }
  sqlsrv_free_stmt($stmt);
}
$totalPages = max(1, (int)ceil($total / $per));
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Admin System Logs</title>
    <link rel="stylesheet" href="css/StyleADLGS.css">
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
          src="<?= ($unreadCount > 0) ? 'img/belldot.png' : 'img/bell.png' ?>"
          class="imgh3"
          alt="Notificaciones"
        />
      </button>

      <div class="notification-dropdown" id="notif-dropdown" style="display:none;">
        <?php if ($unreadCount === 0): ?>
          <div class="notif-empty" style="padding:10px;">No hay notificaciones nuevas.</div>

        <?php elseif ($rolActual === 1): ?>
          <!-- ADMIN: desde Modificaciones -->
          <ul class="notif-list" style="list-style:none; margin:0; padding:0; max-height:260px; overflow:auto;">
            <?php foreach ($notifList as $n): ?>
              <?php
                $f = $n['fecha'] ?? null;
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
                  <strong><?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?></strong>
                  <?= $qtyTxt ?> — <?= htmlspecialchars($n['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="notif-date" style="font-size:0.8rem; opacity:0.7;"><?= $fechaTxt ?></div>
              </li>
            <?php endforeach; ?>
          </ul>

        <?php else: ?>
          <!-- USUARIOS 2 y 3: desde Notificaciones -->
          <ul class="notif-list" style="list-style:none; margin:0; padding:0; max-height:260px; overflow:auto;">
            <?php foreach ($notifList as $n): ?>
              <?php
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

<main class="main-logs">
  <div class="contenedor-titulo-logs">
    <img src="img/log.png" alt="Icono Logs" class="icono-logs" />
    <h1 class="titulo">REGISTROS DEL SISTEMA</h1>
  </div>

  <form method="get" class="filtros-logs" style="display:grid;grid-template-columns:repeat(6,minmax(140px,1fr));gap:8px;margin:12px 0;">
    <label>Desde
      <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>">
    </label>
    <label>Hasta
      <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
    </label>
    <label>Módulo
      <select name="modulo">
        <?php foreach($modulos as $m): ?>
          <option value="<?= $m ?>" <?= $m===$mod? 'selected':'' ?>><?= $m===''? 'Todos' : $m ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Acción
      <input type="text" name="accion" placeholder="p.ej. LOGIN_OK" value="<?= htmlspecialchars($acc) ?>">
    </label>
    <label>Estado
      <select name="estado">
        <option value="-1" <?= $est===-1?'selected':'' ?>>Todos</option>
        <option value="1"  <?= $est===1?'selected':'' ?>>OK</option>
        <option value="0"  <?= $est===0?'selected':'' ?>>ERROR</option>
      </select>
    </label>
    <label>Búsqueda
      <input type="text" name="q" placeholder="usuario / apodo / texto" value="<?= htmlspecialchars($q) ?>">
    </label>
    <div style="grid-column:1/-1;text-align:right;margin-top:4px;">
      <button type="submit" class="report-btn">FILTRAR</button>
    </div>
  </form>

  <div class="contenedor-logs" style="overflow:auto; max-height:64vh;">
    <table class="tabla-logs" style="width:100%;border-collapse:collapse;">
      <thead>
        <tr>
          <th style="text-align:left;padding:6px;border-bottom:1px solid #ddd;">Fecha/Hora</th>
          <th style="text-align:left;padding:6px;border-bottom:1px solid #ddd;">Usuario</th>
          <th style="text-align:left;padding:6px;border-bottom:1px solid #ddd;">Módulo</th>
          <th style="text-align:left;padding:6px;border-bottom:1px solid #ddd;">Acción</th>
          <th style="text-align:left;padding:6px;border-bottom:1px solid #ddd;">Descripción</th>
          <th style="text-align:left;padding:6px;border-bottom:1px solid #ddd;">IP</th>
          <th style="text-align:left;padding:6px;border-bottom:1px solid #ddd;">Estado</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" style="padding:12px;text-align:center;color:#666;">Sin resultados</td></tr>
        <?php else: foreach($rows as $L): ?>
          <tr>
            <td style="padding:6px;border-bottom:1px solid #eee;white-space:nowrap;"><?= htmlspecialchars($L['fechaHora']) ?></td>
            <td style="padding:6px;border-bottom:1px solid #eee;">
              <?= htmlspecialchars($L['usuario'] ?? '---') ?>
              <?php if(!empty($L['apodo'])): ?> (<?= htmlspecialchars($L['apodo']) ?>)<?php endif; ?>
            </td>
            <td style="padding:6px;border-bottom:1px solid #eee;"><?= htmlspecialchars($L['modulo']) ?></td>
            <td style="padding:6px;border-bottom:1px solid #eee;"><?= htmlspecialchars($L['accion']) ?></td>
            <td style="padding:6px;border-bottom:1px solid #eee;"><?= htmlspecialchars($L['descripcion']) ?></td>
            <td style="padding:6px;border-bottom:1px solid #eee;"><?= htmlspecialchars($L['ipUsuario']) ?></td>
            <td style="padding:6px;border-bottom:1px solid #eee;"><?= ((int)$L['estado']===1?'OK':'ERROR') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="paginacion" style="margin-top:10px;display:flex;gap:6px;justify-content:center;">
    <?php for($p=1;$p<=$totalPages;$p++): ?>
      <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>"
         style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;<?= $p===$page?'background:#eee;font-weight:bold;':'' ?>">
        <?= $p ?>
      </a>
    <?php endfor; ?>
  </div>
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
// Notificaciones
const notifToggle   = document.getElementById('notif-toggle');
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

// Confirmar lectura (roles 2 y 3): marca estatusRevision=1 y redirige a inventario
function ackUserNotif(idNotificacion) {
  fetch('php/ack_user_notif.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
    body: 'id=' + encodeURIComponent(idNotificacion)
  })
  .then(r => r.json()).catch(() => ({}))
  .finally(() => { window.location.href = 'inventory.php'; });
}
</script>
</body>
</html>
