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
$tsqlProductos = "SELECT idCodigo, codigo, descripcion, tipo, linea, sublinea, unidad FROM Productos ORDER BY codigo ASC";
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

<main class="entrada-container">
    <div class="entrada-title">
        <h2>ENTRADAS</h2>
    </div>
    <section class="entrada-tabs">
        <button class="active-tab">ENTRADA EXISTENTE</button>
        <a href="nwentry.php"><button class="new-bttn">ENTRADA NUEVA</button></a>
    </section>

    <form class="entrada-form" action="php/registrar_entrada_existente.php" method="POST" onsubmit="return validateForm()">
    <div class="entrada-row">
        <div class="entrada-col">
            <label for="idCodigo">CÓDIGO *</label>
            <select name="idCodigo" id="codigo" required onchange="llenarCampos()">
                <option value="">Seleccione un producto</option>
                <?php foreach ($productos as $producto): ?>
                    <option value="<?= (int)$producto['idCodigo'] ?>" 
                            data-descripcion="<?= htmlspecialchars($producto['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-tipo="<?= htmlspecialchars($producto['tipo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-linea="<?= htmlspecialchars($producto['linea'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-sublinea="<?= htmlspecialchars($producto['sublinea'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-unidad="<?= htmlspecialchars($producto['unidad'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($producto['codigo'] ?? '') ?> - <?= htmlspecialchars($producto['descripcion'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="entrada-col">
            <label for="fecha">FECHA *</label>
            <input type="date" id="fecha" name="fecha" value="<?= $fecha_actual ?>" required />
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
        <label for="cantidad">CANTIDAD *</label>
        <input type="number" name="cantidad" id="cantidad" required min="1" step="1" />
        
        <label for="idProveedor">PROVEEDOR *</label>
        <select name="idProveedor" id="idProveedor" required>
            <option value="">Seleccione un proveedor</option>
            <?php foreach ($proveedores as $prov): ?>
                <option value="<?= (int)$prov['idProveedor'] ?>">
                    <?= htmlspecialchars($prov['razonSocial'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </option>
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
function llenarCampos() {
    const select = document.getElementById("codigo");
    const option = select.options[select.selectedIndex];

    if (option && option.value) {
        document.getElementById("descripcion").value = option.getAttribute("data-descripcion") || "";
        document.getElementById("tipo").value = option.getAttribute("data-tipo") || "";
        document.getElementById("linea").value = option.getAttribute("data-linea") || "";
        document.getElementById("sublinea").value = option.getAttribute("data-sublinea") || "";
        document.getElementById("unidad").value = option.getAttribute("data-unidad") || "";
    } else {
        // Clear fields if no selection
        document.getElementById("descripcion").value = "";
        document.getElementById("tipo").value = "";
        document.getElementById("linea").value = "";
        document.getElementById("sublinea").value = "";
        document.getElementById("unidad").value = "";
    }
}

function validateForm() {
    const idCodigo = document.getElementById("codigo").value;
    const cantidad = document.getElementById("cantidad").value;
    const idProveedor = document.getElementById("idProveedor").value;
    
    if (!idCodigo || idCodigo === "") {
        alert("Por favor seleccione un código de producto");
        return false;
    }
    
    if (!cantidad || parseInt(cantidad) <= 0) {
        alert("Por favor ingrese una cantidad válida (mayor a 0)");
        return false;
    }
    
    if (!idProveedor || idProveedor === "") {
        alert("Por favor seleccione un proveedor");
        return false;
    }
    
    return confirm("¿Está seguro de registrar esta entrada?\n\nCantidad: " + cantidad);
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
