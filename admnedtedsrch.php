<?php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
  header("Location: index.php"); exit();
}
$idRol = (int)($_SESSION['rol'] ?? 0);
if ($idRol !== 1) { header("Location: acceso_denegado.php"); exit(); }

/* ------------------ Notificaciones (nuevo esquema) ------------------ */
$rolActual   = (int)($_SESSION['rol'] ?? 0);
$unreadCount = 0;
$notifList   = [];

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
  // Admin: pendientes en Modificaciones
  $stmtCount = sqlsrv_query($conn, "SELECT COUNT(*) AS c FROM Modificaciones WHERE solicitudRevisada = 0");
  $stmtList  = sqlsrv_query($conn, "
    SELECT TOP 10 M.idModificacion, M.descripcion, M.fecha, M.tipo, M.cantidad, P.codigo AS codigoProducto
    FROM Modificaciones M
    LEFT JOIN Productos P ON P.idCodigo = M.idCodigo
    WHERE M.solicitudRevisada = 0
    ORDER BY M.fecha DESC
  ");
  if ($stmtCount) { $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC); $unreadCount = (int)($row['c'] ?? 0); sqlsrv_free_stmt($stmtCount); }
  if ($stmtList)  { while ($r = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) $notifList[] = $r; sqlsrv_free_stmt($stmtList); }
}

/* ------------------ Productos para el buscador ------------------ */
$productos = [];
if (!$conn) { $conn = sqlsrv_connect($serverName, $connectionOptions); }
if ($conn) {
  $stmtProd = sqlsrv_query($conn, "SELECT idCodigo, codigo, descripcion FROM Productos ORDER BY codigo");
  if ($stmtProd) {
    while ($row = sqlsrv_fetch_array($stmtProd, SQLSRV_FETCH_ASSOC)) $productos[] = $row;
    sqlsrv_free_stmt($stmtProd);
  }
  sqlsrv_close($conn);
}

// Página de destino al buscar (cámbiala si necesitas otra)
$targetPage = 'modedtdtls.php';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
  <title>SIA Admin Elements Edition Edit Search</title>
  <link rel="stylesheet" href="css/StyleADEDEDSH.css">
  <style>
    .notification-container{position:relative}
    .notification-dropdown{display:none;position:absolute;right:0;top:40px;background:#fff;border:1px solid #e5e5e5;border-radius:10px;width:320px;box-shadow:0 10px 25px rgba(0,0,0,.08);z-index:20}
    .notif-item{padding:8px 10px;cursor:pointer;border-bottom:1px solid #eaeaea}
    .notif-item:hover{background:#fafafa}
  </style>
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
        <img src="<?= ($unreadCount > 0) ? 'img/belldot.png' : 'img/bell.png' ?>" class="imgh3" alt="Notificaciones" />
      </button>
      <div class="notification-dropdown" id="notif-dropdown">
        <?php if ($unreadCount === 0): ?>
          <div class="notif-empty" style="padding:10px;">No hay notificaciones nuevas.</div>
        <?php else: ?>
          <ul class="notif-list" style="list-style:none;margin:0;padding:0;max-height:260px;overflow:auto;">
            <?php foreach ($notifList as $n): ?>
              <?php
                $f = $n['fecha'] ?? null;
                $fechaTxt = ($f instanceof DateTime) ? $f->format('Y-m-d H:i')
                          : (($dt = @date_create(is_string($f) ? $f : 'now')) ? $dt->format('Y-m-d H:i') : '');
                $tipoTxt  = strtoupper((string)($n['tipo'] ?? ''));
                $qtyTxt   = isset($n['cantidad']) ? ' • Cant.: '.(int)$n['cantidad'] : '';
                $codigo   = (string)($n['codigoProducto'] ?? '');
              ?>
              <li class="notif-item" onclick="location.href='admnrqst.php'">
                <div class="notif-desc" style="font-size:0.95rem;">
                  [<?= htmlspecialchars($tipoTxt) ?>]
                  <strong><?= htmlspecialchars($codigo) ?></strong><?= $qtyTxt ?> — <?= htmlspecialchars($n['descripcion'] ?? '') ?>
                </div>
                <div class="notif-date" style="font-size:0.8rem;opacity:.7;"><?= $fechaTxt ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <p><?= htmlspecialchars($_SESSION['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

    <div class="user-menu-container">
      <button class="icon-btn" id="user-toggle" type="button"><img src="img/userB.png" class="imgh2" alt="Usuario" /></button>
      <div class="user-dropdown" id="user-dropdown" style="display:none;">
        <p><strong>Tipo de usuario:</strong> <?= (int)($_SESSION['rol'] ?? 0) ?></p>
        <p><strong>Apodo:</strong> <?= htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        <a href="passchng.php"><button class="user-option" type="button">CAMBIAR CONTRASEÑA</button></a>
      </div>
    </div>

    <div class="menu-container">
      <button class="icon-btn" id="menu-toggle" type="button"><img src="img/menu.png" alt="Menú" /></button>
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

<main class="main-container">
  <h1 class="titulo-seccion">EDITAR ELEMENTOS</h1>

  <div class="contenedor-formulario">
    <h2 class="subtitulo">EDITAR DETALLES DE LOS ELEMENTOS</h2>

    <!-- Filtro de texto -->
    <label for="filtro">Buscar código o descripción</label>
    <input id="filtro" class="input-ancho-grande" type="text" placeholder="Escribe para filtrar…" oninput="filtrar()">

    <!-- Formulario real -->
    <form id="formBuscar" method="get" action="<?= $targetPage ?>">
      <label for="codigo">CÓDIGO</label>
      <select id="codigo" name="idCodigo" class="input-ancho-grande" required size="10">
        <?php foreach ($productos as $p): ?>
          <option value="<?= (int)$p['idCodigo'] ?>">
            <?= htmlspecialchars($p['codigo']) ?> — <?= htmlspecialchars($p['descripcion']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <div class="botones-formulario">
        <a href="admnedt.php"><button type="button" class="boton-negro">CANCELAR</button></a>
        <button type="submit" class="boton-negro">BUSCAR</button>
      </div>
    </form>
  </div>
</main>

<script>
  // Nav
  const toggle = document.getElementById('menu-toggle');
  const dropdown = document.getElementById('dropdown-menu');
  toggle.addEventListener('click', () => dropdown.style.display = dropdown.style.display === 'flex' ? 'none' : 'flex');
  window.addEventListener('click', (e) => { if (!toggle.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display='none'; });

  // Usuario
  const userToggle = document.getElementById('user-toggle');
  const userDropdown = document.getElementById('user-dropdown');
  userToggle.addEventListener('click', () => userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block');
  window.addEventListener('click', (e) => { if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) userDropdown.style.display='none'; });

  // Notificaciones
  const notifToggle = document.getElementById('notif-toggle');
  const notifDropdown = document.getElementById('notif-dropdown');
  notifToggle.addEventListener('click', () => notifDropdown.style.display = notifDropdown.style.display === 'block' ? 'none' : 'block');
  window.addEventListener('click', (e) => { if (!notifToggle.contains(e.target) && !notifDropdown.contains(e.target)) notifDropdown.style.display='none'; });

  // Filtro client-side del select
  function filtrar() {
    const q = document.getElementById('filtro').value.toLowerCase();
    const sel = document.getElementById('codigo');
    for (const opt of sel.options) {
      const txt = opt.text.toLowerCase();
      opt.style.display = txt.includes(q) ? '' : 'none';
    }
  }
</script>
</body>
</html>
