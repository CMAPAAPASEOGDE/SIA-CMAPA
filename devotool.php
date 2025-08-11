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

// Conexión a SQL Server
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

// Obtener herramientas prestadas (enInventario = 0)
$herramientas = [];
$sql = "SELECT 
            H.idHerramienta,           -- GUID (UNIQUEIDENTIFIER)
            H.identificadorUnico,
            H.idCodigo,
            P.codigo,
            P.descripcion
        FROM HerramientasUnicas H
        INNER JOIN Productos P ON H.idCodigo = P.idCodigo
        WHERE H.enInventario = 0
        ORDER BY P.descripcion, H.identificadorUnico";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $herramientas[] = $row;
}
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Tool Devolution</title>
    <link rel="stylesheet" href="css/StyleDVTL.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>
<header>
  <div class="brand">
    <img src="img/cmapa.png" class="logo" alt="CMAPA" />
    <h1>SIA - CMAPA</h1>
  </div>
  <div class="header-right">
    <div class="notification-container">
      <button class="icon-btn" id="notif-toggle" type="button">
        <img src="img/bell.png" class="imgh3" alt="Notificaciones" />
      </button>
      <div class="notification-dropdown" id="notif-dropdown"></div>
    </div>
    <p><?= htmlspecialchars($_SESSION['usuario'] ?? 'Usuario') ?></p>
    <div class="user-menu-container">
      <button class="icon-btn" id="user-toggle" type="button">
        <img src="img/userB.png" class="imgh2" alt="Usuario" />
      </button>
      <div class="user-dropdown" id="user-dropdown">
        <p><strong>Rol:</strong> <?= (int)($_SESSION['rol'] ?? 0) ?></p>
        <p><strong>Apodo:</strong> <?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></p>
        <a href="passchng.php"><button class="user-option" type="button">CAMBIAR CONTRASEÑA</button></a>
      </div>
    </div>
    <div class="menu-container">
      <button class="icon-btn" id="menu-toggle" type="button">
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

<main class="devolucion-container">
  <div class="devolucion-title">
    <h2>DEVOLUCIÓN DE HERRAMIENTAS</h2>
  </div>

  <form id="devolucionForm" class="devolucion-form">
    <!-- Identificador -->
    <label for="codigo">IDENTIFICADOR</label>
    <select id="codigo" name="idHerramienta" required>
      <option value="">-- Selecciona una herramienta prestada --</option>
      <?php foreach ($herramientas as $h): ?>
        <option
          value="<?= htmlspecialchars($h['idHerramienta']) ?>"
          data-ident="<?= htmlspecialchars($h['identificadorUnico']) ?>"
          data-idcodigo="<?= (int)$h['idCodigo'] ?>"
          data-codigo="<?= htmlspecialchars($h['codigo']) ?>"
          data-desc="<?= htmlspecialchars($h['descripcion']) ?>"
        >
          <?= htmlspecialchars($h['identificadorUnico']) ?> — <?= htmlspecialchars($h['descripcion']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <!-- Nombre / Descripción -->
    <label for="nombre">NOMBRE O DESCRIPCIÓN</label>
    <input type="text" id="nombre" value="" readonly>

    <!-- Observaciones -->
    <label for="observaciones">OBSERVACIONES</label>
    <textarea id="observaciones" name="observaciones" rows="5" maxlength="200"></textarea>

    <!-- Estado y Fecha -->
    <div class="form-row">
      <div class="form-group">
        <label for="estado">ESTADO</label>
        <select id="estado" name="estado" required>
          <option value="">-- Seleccione --</option>
          <option value="NECESITA CAMBIO">NECESITA CAMBIO</option>
          <option value="FUNCIONAL">FUNCIONAL</option>
          <option value="EN REPARACIÓN">EN REPARACIÓN</option>
        </select>
      </div>
      <div class="form-group">
        <label for="fecha">FECHA DE RETORNO</label>
        <input type="date" id="fecha" name="fechaRetorno" value="<?= date('Y-m-d') ?>">
      </div>
    </div>

    <!-- Botones -->
    <div class="form-buttons">
      <a href="warehouse.php"><button type="button" class="btn cancel">CANCELAR</button></a>
      <button type="submit" class="btn confirm" id="btnConfirm" <?= empty($herramientas) ? 'disabled' : '' ?>>CONFIRMAR</button>
    </div>

    <?php if (empty($herramientas)): ?>
      <p class="hint">No hay herramientas pendientes de devolución.</p>
    <?php endif; ?>
  </form>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Menús
const toggle = document.getElementById('menu-toggle');
const dropdown = document.getElementById('dropdown-menu');
toggle.addEventListener('click', () => {
  dropdown.style.display = (dropdown.style.display === 'flex') ? 'none' : 'flex';
});
window.addEventListener('click', (e) => {
  if (!toggle.contains(e.target) && !dropdown.contains(e.target)) {
    dropdown.style.display = 'none';
  }
});
const userToggle = document.getElementById('user-toggle');
const userDropdown = document.getElementById('user-dropdown');
userToggle.addEventListener('click', () => {
  userDropdown.style.display = (userDropdown.style.display === 'block') ? 'none' : 'block';
});
window.addEventListener('click', (e) => {
  if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) {
    userDropdown.style.display = 'none';
  }
});
const notifToggle = document.getElementById('notif-toggle');
const notifDropdown = document.getElementById('notif-dropdown');
notifToggle.addEventListener('click', () => {
  notifDropdown.style.display = (notifDropdown.style.display === 'block') ? 'none' : 'block';
});
window.addEventListener('click', (e) => {
  if (!notifToggle.contains(e.target) && !notifDropdown.contains(e.target)) {
    notifDropdown.style.display = 'none';
  }
});
</script>

<script>
$(function () {
  // Autocompletar descripción al seleccionar
  $('#codigo').on('change', function() {
    const selected = $(this).find('option:selected');
    $('#nombre').val(selected.data('desc') || '');
  });

  // Inicializa descripción si ya hay selección
  $('#codigo').trigger('change');

  // Enviar formulario vía AJAX
  $('#devolucionForm').on('submit', function(e) {
    e.preventDefault();

    const selected = $('#codigo').find('option:selected');
    const idHerramienta = $('#codigo').val();
    const estado = $('#estado').val();

    if (!idHerramienta || !estado) {
      alert('Por favor complete los campos obligatorios');
      return;
    }

    const formData = {
      idHerramienta: idHerramienta,                       // GUID como texto
      identificadorUnico: selected.data('ident') || '',   // respaldo opcional
      observaciones: $('#observaciones').val(),
      estado: estado,
      fechaRetorno: $('#fecha').val(),
      registradoPor: <?= (int)($_SESSION['rol'] ?? 0) ?>  // idRol
    };

    $('#btnConfirm').prop('disabled', true);

    $.ajax({
      type: 'POST',
      url: 'procesar_devolucion.php',
      data: formData,
      dataType: 'json'
    }).done(function(response) {
      if (response && response.success) {
        window.location.href = 'devtlcnf.php';
      } else {
        window.location.href = 'devtlerr.php';
      }
    }).fail(function() {
      window.location.href = 'devtlerr.php';
    }).always(function() {
      $('#btnConfirm').prop('disabled', false);
    });
  });
});
</script>
</body>
</html>
