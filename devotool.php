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

// Conexión CORREGIDA para SQL Server
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

// Obtener herramientas prestadas - CORREGIDO para SQL Server
$herramientas = [];
$sql = "SELECT idHerramienta, codigo, descripcion 
        FROM HerramientasUnicas 
        WHERE enInventario = 0";
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

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Tool Devolution</title>
    <link rel="stylesheet" href="css/StyleDVTL.css">
</head>

<body>
<header>
  <div class="brand">
    <img src="img/cmapa.png" class="logo" />
    <h1>SIA - CMAPA</h1>
  </div>
  <div class="header-right">
    <div class="notification-container">
      <button class="icon-btn" id="notif-toggle">
        <img src="img/bell.png" class="imgh3" alt="Notificaciones" />
      </button>
      <div class="notification-dropdown" id="notif-dropdown"></div>
    </div>
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

<main class="devolucion-container">
    <div class="devolucion-title">
        <h2>DEVOLUCIÓN DE HERRAMIENTAS</h2>
    </div>
    
    <form id="devolucionForm" class="devolucion-form">
        <!-- Código -->
        <label for="codigo">CÓDIGO</label>
        <select id="codigo" name="idHerramienta" required>
            <option value="">-- Seleccione una herramienta --</option>
            <?php foreach ($herramientas as $h): ?>
                <option value="<?= $h['idHerramienta'] ?>" data-desc="<?= htmlspecialchars($h['descripcion']) ?>">
                    <?= htmlspecialchars($h['codigo']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <!-- Nombre -->
        <label for="nombre">NOMBRE O DESCRIPCIÓN</label>
        <input type="text" id="nombre" value="" readonly>
        <!-- Observaciones -->
        <label for="observaciones">OBSERVACIONES</label>
        <textarea id="observaciones" name="observaciones" rows="5"></textarea>
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
            <button type="submit" class="btn confirm">CONFIRMAR</button>
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

<!--para accion de confirmar-->
<script>
  document.querySelector('.devolucion-form').addEventListener('submit', function (e) {
    e.preventDefault(); // prevenir envío
    alert('Formulario enviado correctamente.');
    // Aquí podrías enviar por fetch(), guardar en base de datos, etc.
  });

  document.querySelector('.cancel').addEventListener('click', function () {
    window.history.back(); // volver atrás o redireccionar
  });
</script>

<script>
  // Autocompletar descripción al seleccionar herramienta
  $('#codigo').change(function() {
    const selected = $(this).find('option:selected');
    $('#nombre').val(selected.data('desc') || '');
  });

  // Enviar formulario
  $('#devolucionForm').submit(function(e) {
    e.preventDefault();
    
    const formData = {
      idHerramienta: $('#codigo').val(),
      observaciones: $('#observaciones').val(),
      estado: $('#estado').val(),
      fechaRetorno: $('#fecha').val(),
      registradoPor: <?= $_SESSION['user_id'] ?>
    };

    if (!formData.idHerramienta || !formData.estado) {
      alert('Por favor complete todos los campos obligatorios');
      return;
    }

    $.ajax({
      type: 'POST',
      url: 'procesar_devolucion.php',
      data: formData,
      dataType: 'json',
      success: function(response) {
        if (response.success) {
          alert('Devolución registrada correctamente');
          window.location.href = 'warehouse.php';
        } else {
          alert('Error: ' + response.message);
        }
      },
      error: function() {
        alert('Error al procesar la solicitud');
      }
    });
  });
</script>

<script>
  $(document).ready(function() {
    // Autocompletar descripción
    $('#codigo').change(function() {
      const selected = $(this).find('option:selected');
      $('#nombre').val(selected.data('desc') || '');
    });

    // Enviar formulario
    $('#devolucionForm').submit(function(e) {
      e.preventDefault();
      
      const formData = {
        idHerramienta: $('#codigo').val(),
        observaciones: $('#observaciones').val(),
        estado: $('#estado').val(),
        fechaRetorno: $('#fecha').val(),
        registradoPor: <?= $_SESSION['user_id'] ?>
      };

      if (!formData.idHerramienta || !formData.estado) {
        alert('Por favor complete todos los campos obligatorios');
        return;
      }

      $.ajax({
        type: 'POST',
        url: 'procesar_devolucion.php',
        data: formData,
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            alert('Devolución registrada correctamente');
            window.location.href = 'warehouse.php';
          } else {
            alert('Error: ' + response.message);
          }
        },
        error: function(xhr, status, error) {
          alert('Error al procesar: ' + error);
        }
      });
    });
  });
</script>
</body>
</html>
