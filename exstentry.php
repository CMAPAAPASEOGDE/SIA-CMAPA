<?php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1, 2])) {
    header("Location: acceso_denegado.php");
    exit();
}

// Conexión a la base de datos
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = array(
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
);
$conn = sqlsrv_connect($serverName, $connectionOptions);

// Obtener productos
$productos = [];
$tsqlProductos = "SELECT idCodigo, codigo, descripcion, tipo, linea, sublinea, unidad FROM Productos";
$stmtProd = sqlsrv_query($conn, $tsqlProductos);
while ($row = sqlsrv_fetch_array($stmtProd, SQLSRV_FETCH_ASSOC)) {
    $productos[] = $row;
}

// Obtener proveedores
$proveedores = [];
$tsqlProv = "SELECT idProveedor, razonSocial FROM Proveedores";
$stmtProv = sqlsrv_query($conn, $tsqlProv);
while ($row = sqlsrv_fetch_array($stmtProv, SQLSRV_FETCH_ASSOC)) {
    $proveedores[] = $row;
}

$rolActual   = (int)($_SESSION['rol'] ?? 0);
$notifTarget = ($rolActual === 1) ? 'admnrqst.php' : 'mis_notifs.php';

$unreadCount = 0;
$notifList   = [];

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

$fecha_actual = date('Y-m-d');
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Exist Entry</title>
    <link rel="stylesheet" href="css/StyleEXET.css">
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

<main class="entrada-container">
    <div class="entrada-title">
        <h2>ENTRADAS</h2>
    </div>
    <section class="entrada-tabs">
        <button class="active-tab">ENTRADA EXISTENTE</button>
        <a href="nwentry.php"><button class="new-bttn">ENTRADA NUEVA</button></a>
    </section>

    <form class="entrada-form" action="php/registrar_entrada_existente.php" method="POST">
        <div class="entrada-row">
            <div class="entrada-col">
                <label for="idCodigo">CÓDIGO</label>
                <select name="idCodigo" id="codigo" required onchange="llenarCampos()">
                    <option value="">Seleccione</option>
                    <?php foreach ($productos as $producto): ?>
                        <option value="<?= $producto['idCodigo'] ?>" 
                                data-descripcion="<?= $producto['descripcion'] ?>"
                                data-tipo="<?= $producto['tipo'] ?>"
                                data-linea="<?= $producto['linea'] ?>"
                                data-sublinea="<?= $producto['sublinea'] ?>"
                                data-unidad="<?= $producto['unidad'] ?>">
                            <?= htmlspecialchars($producto['codigo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="entrada-col">
                <label for="fecha">FECHA</label>
                <input type="date" id="fecha" name="fecha" value="<?= $fecha_actual ?>" readonly />
            </div>
        </div>

        <div class="entrada-row">
            <label>DESCRIPCIÓN</label>
            <input type="text" id="descripcion" readonly />
            <label>TIPO</label>
            <input type="text" id="tipo" readonly />
        </div>
        <div class="entrada-row">
            <label>LINEA</label>
            <input type="text" id="linea" readonly />
            <label>SUBLINEA</label>
            <input type="text" id="sublinea" readonly />
            <label>UNIDAD</label>
            <input type="text" id="unidad" readonly />
        </div>
        <div class="entrada-row">
            <label for="cantidad">CANTIDAD</label>
            <input type="number" name="cantidad" required min="1" />
            <label for="idProveedor">PROVEEDOR</label>
            <select name="idProveedor" required>
                <option value="">Seleccione</option>
                <?php foreach ($proveedores as $prov): ?>
                    <option value="<?= $prov['idProveedor'] ?>"><?= htmlspecialchars($prov['razonSocial']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="entrada-buttons">
            <a href="warehouse.php"><button type="button">CANCELAR</button></a>
            <button type="submit">CONFIRMAR</button>
        </div>
    </form>
</main>

<script>
function llenarCampos() {
    const select = document.getElementById("codigo");
    const option = select.options[select.selectedIndex];

    document.getElementById("descripcion").value = option.getAttribute("data-descripcion") || "";
    document.getElementById("tipo").value = option.getAttribute("data-tipo") || "";
    document.getElementById("linea").value = option.getAttribute("data-linea") || "";
    document.getElementById("sublinea").value = option.getAttribute("data-sublinea") || "";
    document.getElementById("unidad").value = option.getAttribute("data-unidad") || "";
}
</script>

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
