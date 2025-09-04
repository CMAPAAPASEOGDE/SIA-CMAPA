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
if (!in_array($idRol, [1, 2])) {
    header("Location: acceso_denegado.php");
    exit();
}
require_once __DIR__.'/php/month_close_utils.php';
$conn = db_conn_or_die();
$cierres = list_cierres($conn);
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Reports Closed Month</title>
    <link rel="stylesheet" href="css/StyleRPMNCLS.css">
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

<main class="reportes-container">
  <div class="reportes-title">
    <img src="img/documentation.png" alt="Icono Reportes" />
    <h2>Reportes</h2>
  </div>
  <div class="reportes-menu">
    <a href="rprtsinv.php"><button class="report-btn">INVENTARIO DEL ALMACÉN</button></a>
    <a href="rprtskrdx.php"><button class="report-btn">KARDEX DEL PRODUCTO</button></a>
    <a href="rprtswhms.php"><button class="report-btn">MOVIMIENTOS DEL ALMACÉN</button></a>
    <button class="report-btn">CIERRE DE MES</button>
  </div>

  <p class="descarga-exito">SELECCIONA EL CIERRE CONFIRMADO A DESCARGAR</p>

  <form method="post" style="text-align:center;">
    <label>CIERRE
      <select name="idCierre" required>
        <?php foreach($cierres as $c):
          $fi = ($c['fechaInicio'] instanceof DateTime) ? $c['fechaInicio']->format('Y-m-d') : substr((string)$c['fechaInicio'],0,10);
          $ff = ($c['fechaFin']    instanceof DateTime) ? $c['fechaFin']->format('Y-m-d')    : substr((string)$c['fechaFin'],0,10);
          $txt = $fi.' a '.$ff.' — '.$c['etiqueta'];
        ?>
          <option value="<?= (int)$c['idCierre'] ?>"><?= htmlspecialchars($txt) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <div class="report-buttons" style="margin-top:12px;">
      <button formaction="php/exportar_cierre_pdf.php"  type="submit">PDF</button>
      <button formaction="php/exportar_cierre_excel.php" type="submit">XLSX</button>
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
</body>
</html>
