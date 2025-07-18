<?php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$idRol = (int)($_SESSION['rol'] ?? 0);
if ($idRol !== 1) {
    header("Location: acceso_denegado.php");
    exit();
}

// Conexión
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);

// Obtener productos
$productos = [];
$sql = "SELECT idCodigo, codigo, descripcion, linea, sublinea FROM Productos";
$stmt = sqlsrv_query($conn, $sql);
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $productos[] = $row;
}

$fecha_actual = date('Y-m-d');
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Admin Elements Edition Exits</title>
    <link rel="stylesheet" href="css/StyleADEDEX.css">
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

<main class="main-container">
  <h1 class="titulo-seccion">SALIDA SIN RESTRICCIONES</h1>
  <form class="contenedor-formulario" action="php/registrar_salida_admin.php" method="POST">
    <label for="codigo">CÓDIGO</label>
    <select name="idCodigo" id="codigo" class="input-ancho-grande" required onchange="completarDatos(this)">
      <option value="">Seleccionar...</option>
      <?php foreach ($productos as $p): ?>
        <option value="<?= $p['idCodigo'] ?>"
          data-nombre="<?= htmlspecialchars($p['descripcion']) ?>"
          data-linea="<?= htmlspecialchars($p['linea']) ?>"
          data-sublinea="<?= htmlspecialchars($p['sublinea']) ?>">
          <?= htmlspecialchars($p['codigo']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <div class="grupo-campos">
      <div>
        <label for="nombre">NOMBRE</label>
        <input type="text" id="nombre" readonly>
      </div>
      <div>
        <label for="linea">LÍNEA</label>
        <input type="text" id="linea" readonly>
      </div>
      <div>
        <label for="sublinea">SUBLÍNEA</label>
        <input type="text" id="sublinea" readonly>
      </div>
    </div>

    <div class="grupo-campos">
      <div>
        <label for="cantidad">CANTIDAD</label>
        <input type="number" name="cantidad" id="cantidad" required min="1">
      </div>
      <div>
        <label for="fecha">FECHA DE REGISTRO</label>
        <input type="date" name="fecha" id="fecha" value="<?= $fecha_actual ?>" required>
      </div>
    </div>

    <div class="botones-formulario">
      <a href="admnedt.php"><button type="button" class="boton-negro">CANCELAR</button></a>
      <button type="submit" class="boton-negro">CONFIRMAR</button>
    </div>
  </form>
</main>

<script>
function completarDatos(select) {
  const option = select.options[select.selectedIndex];
  document.getElementById('nombre').value = option.dataset.nombre || '';
  document.getElementById('linea').value = option.dataset.linea || '';
  document.getElementById('sublinea').value = option.dataset.sublinea || '';
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
