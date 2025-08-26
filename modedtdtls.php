<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Verificar el rol del usuario
$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1, 2], true)) {
    header("Location: acceso_denegado.php");
    exit();
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
if ($conn === false) { die(print_r(sqlsrv_errors(), true)); }

/* =========================
   CARGAR PRODUCTOS
   ========================= */
$productos = [];
$sqlProd = "SELECT idCodigo, codigo, descripcion FROM Productos ORDER BY codigo";
$stmtProd = sqlsrv_query($conn, $sqlProd);
if ($stmtProd === false) { die(print_r(sqlsrv_errors(), true)); }
while ($row = sqlsrv_fetch_array($stmtProd, SQLSRV_FETCH_ASSOC)) {
    $productos[] = $row;
}
sqlsrv_free_stmt($stmtProd);

// Cerrar conexión (no se usa más en este PHP)
sqlsrv_close($conn);
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <title>SIA Modifications Edit Details</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <link rel="stylesheet" href="css/StyleMDEDDTLS.css">
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
    <p> <?= $_SESSION['usuario'] ?> </p>
    <div class="user-menu-container">
      <button class="icon-btn" id="user-toggle">
        <img src="img/userB.png" class="imgh2" alt="Usuario" />
      </button>
      <div class="user-dropdown" id="user-dropdown">
        <p><strong>Usuario:</strong> <?= $_SESSION[ 'rol' ]?></p>
        <p><strong>Apodo:</strong> <?= htmlspecialchars($_SESSION['nombre'])?></p>
        <a href="passchng.php"><button class="user-option">CAMBIAR CONTRASEÑA</button></a>
      </div>
    </div>
    <!-- botón hamburguesa -->
    <div class="menu-container">
      <button class="icon-btn" id="menu-toggle">
        <img src="img/menu.png" alt="Menú" />
      </button>
      <div class="dropdown" id="dropdown-menu">
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

<main class="altas-container">
  <div class="altas-title">
    <h2>MODIFICACIONES DE INVENTARIO</h2>
    <h3>EDITAR DETALLES DE LOS ELEMENTOS</h3>
  </div>

  <form id="formDetalles" class="altas-form">
    <label for="idCodigo">CÓDIGO</label>
    <select id="idCodigo" name="idCodigo" required>
      <option value="">-- Selecciona un producto --</option>
      <?php foreach ($productos as $p): ?>
        <option
          value="<?= (int)$p['idCodigo'] ?>"
          data-codigo="<?= htmlspecialchars($p['codigo'], ENT_QUOTES, 'UTF-8') ?>"
          data-desc="<?= htmlspecialchars($p['descripcion'], ENT_QUOTES, 'UTF-8') ?>"
        >
          <?= htmlspecialchars($p['codigo'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($p['descripcion'], ENT_QUOTES, 'UTF-8') ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="codigoVista">CÓDIGO SELECCIONADO</label>
    <input type="text" id="codigoVista" value="" readonly>

    <label for="detalles">DETALLES A EDITAR</label>
    <textarea id="detalles" name="detalles" rows="5" required></textarea>

    <div class="altas-row">
      <div class="altas-column">
        <label for="fechaVista">FECHA DE SOLICITUD</label>
        <input type="date" id="fechaVista" value="<?= date('Y-m-d') ?>" readonly>
        <!-- la fecha real la pondrá el servidor con SYSDATETIME() -->
      </div>
    </div>

    <div class="altas-buttons">
      <a href="modif.php"><button type="button" class="btn">CANCELAR</button></a>
      <button type="submit" class="btn">CONFIRMAR</button>
    </div>
  </form>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Toggle menú
const toggle = document.getElementById('menu-toggle');
const dropdown = document.getElementById('dropdown-menu');
if (toggle && dropdown) {
  toggle.addEventListener('click', () => {
    dropdown.style.display = dropdown.style.display === 'flex' ? 'none' : 'flex';
  });
  window.addEventListener('click', (e) => {
    if (!toggle.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
  });
}

// Toggle usuario
const userToggle = document.getElementById('user-toggle');
const userDropdown = document.getElementById('user-dropdown');
if (userToggle && userDropdown) {
  userToggle.addEventListener('click', () => {
    userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
  });
  window.addEventListener('click', (e) => {
    if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) userDropdown.style.display = 'none';
  });
}

// Toggle notificaciones
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

// UX: muestra código seleccionado
$('#idCodigo').on('change', function () {
  const sel = $(this).find('option:selected');
  $('#codigoVista').val(sel.data('codigo') || '');
});

// Envío AJAX - crea notificación de DETALLE para admin
$('#formDetalles').on('submit', function (e) {
  e.preventDefault();

  const idCodigo = $('#idCodigo').val();
  const detalles = $('#detalles').val().trim();

  if (!idCodigo || !detalles) {
    alert('Completa el código y los detalles a editar.');
    return;
  }

  $.ajax({
    type: 'POST',
    url: 'php/procesar_modedtdtls.php',
    data: {
      idCodigo: idCodigo,
      descripcion: detalles   // Notificaciones.descripcion
      // fecha la pone SYSDATETIME() en el servidor
      // solicitudRevisada = 0
      // idRol destino = 1 (admins)
      // tipo = 'detalle'
      // cantidad = 0 (no aplica cambio de cantidad)
    },
    dataType: 'json'
  }).done(function(resp){
    if (resp && resp.success) {
      window.location.href = 'modeddtcf.php';
    } else {
      alert('Error: ' + (resp.message || 'No se pudo registrar la solicitud') +
      (resp.detail ? '\n\nDetalle:\n' + resp.detail : ''));
    }
  }).fail(function(){
    alert('Error al enviar la solicitud.');
  });
});
</script>
</body>
</html>
