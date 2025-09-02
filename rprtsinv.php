<?php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Inventory Reports</title>
    <link rel="stylesheet" href="css/StyleRPIV.css">
</head>
<body>

<header>
  <div class="brand">
    <img src="img/cmapa.png" class="logo" />
    <h1>SIA - CMAPA</h1>
  </div>

  <div class="header-right">
    <div class="notification-container">
      <button class="icon-btn" id="notif-toggle" type="button">
        <img src="img/bell.png" class="imgh3" alt="Notificaciones" />
      </button>
      <div class="notification-dropdown" id="notif-dropdown" style="display:none;"></div>
    </div>

    <p><?= htmlspecialchars($_SESSION['usuario'] ?? '') ?></p>

    <div class="user-menu-container">
      <button class="icon-btn" id="user-toggle" type="button">
        <img src="img/userB.png" class="imgh2" alt="Usuario" />
      </button>
      <div class="user-dropdown" id="user-dropdown" style="display:none;">
        <p><strong>Usuario:</strong> <?= (int)($_SESSION['rol'] ?? 0) ?></p>
        <p><strong>Apodo:</strong> <?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></p>
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

<main class="reportes-container">
  <h2 class="reportes-title">Reportes</h2>
  <h3 class="reportes-subtitle">INVENTARIO DEL ALMACÉN</h3>
  <p class="reportes-filtro">FILTRAR POR</p>

  <!-- IMPORTANTE: este form hace GET a export_inventario.php y descarga PDF/XLSX -->
  <form class="reporte-filtros" action="php/export_inventario.php" method="get" target="_blank">
    <div class="form-grid">
      <label>CÓDIGO
        <input type="text" name="codigo" placeholder="Ej. 12000689543">
      </label>

      <label>NOMBRE
        <input type="text" name="nombre" placeholder="Descripción contiene...">
      </label>

      <label>LINEA
        <input type="text" name="linea" placeholder="Ej. Hidráulica">
      </label>

      <label>SUBLÍNEA
        <input type="text" name="sublinea" placeholder="Ej. Medidores">
      </label>

      <label>TIPO
        <select name="tipo">
          <option value="">Todos</option>
          <option value="Material">Material</option>
          <option value="Herramienta">Herramienta</option>
        </select>
      </label>

      <label>ESTADO
        <select name="estado">
          <option value="">Todos</option>
          <option value="En stock">En stock</option>
          <option value="Bajo stock">Bajo stock</option>
          <option value="Fuera de stock">Fuera de stock</option>
          <option value="Sobre stock">Sobre stock</option>
        </select>
      </label>
    </div>

    <div class="report-buttons">
  <a href="reports.php"><button type="button">CANCELAR</button></a>

  <!-- PDF -->
  <button type="button" id="btn-pdf">GENERAR PDF</button>

  <!-- XLSX -->
  <button type="button" id="btn-xlsx">GENERAR XLSX</button>
</div>
  </form>
</main>

<script>
  function q(v){ return encodeURIComponent(v || ''); }
  function buildUrl(format){
    // Lee aquí tus filtros reales del formulario:
    const codigo   = document.querySelector('[name="codigo"]')?.value || '';
    const nombre   = document.querySelector('[name="nombre"]')?.value || '';
    const linea    = document.querySelector('[name="linea"]')?.value || '';
    const sublinea = document.querySelector('[name="sublinea"]')?.value || '';
    const tipo     = document.querySelector('[name="tipo"]')?.value || '';
    const estado   = document.querySelector('[name="estado"]')?.value || '';
    return `php/export_inventario.php?format=${format}`+
           `&codigo=${q(codigo)}&nombre=${q(nombre)}&linea=${q(linea)}`+
           `&sublinea=${q(sublinea)}&tipo=${q(tipo)}&estado=${q(estado)}`;
  }
  document.getElementById('btn-pdf').onclick  = () => window.open(buildUrl('pdf'),  '_blank');
  document.getElementById('btn-xlsx').onclick = () => window.open(buildUrl('xlsx'), '_blank');
</script>

<script>
  // menús
  const toggle = document.getElementById('menu-toggle');
  const dropdown = document.getElementById('dropdown-menu');
  toggle.addEventListener('click', () => {
    dropdown.style.display = dropdown.style.display === 'flex' ? 'none' : 'flex';
  });
  window.addEventListener('click', (e) => {
    if (!toggle.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
  });

  const userToggle = document.getElementById('user-toggle');
  const userDropdown = document.getElementById('user-dropdown');
  userToggle.addEventListener('click', () => {
    userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
  });
  window.addEventListener('click', (e) => {
    if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) userDropdown.style.display = 'none';
  });

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
