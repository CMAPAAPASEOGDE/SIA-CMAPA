<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: homepage.php");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>SIA Homepage</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styleHP.css">
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
      <div class="notification-dropdown" id="notif-dropdown">
      </div>
    </div>
    <p>00001</p>
    <div class="user-menu-container">
      <button class="icon-btn" id="user-toggle">
        <img src="img/userB.png" class="imgh2" alt="Usuario" />
      </button>
      <div class="user-dropdown" id="user-dropdown">
        <p><strong>Usuario:</strong> Administrador</p>
        <p><strong>Apodo:</strong> Axel Olvera</p>
        <a href="passchng.html"><button class="user-option">CAMBIAR CONTRASEÑA</button></a>
      </div>
    </div>
    <!-- botón hamburguesa -->
    <div class="menu-container">
      <button class="icon-btn" id="menu-toggle">
        <img src="img/menu.png" alt="Menú" />
      </button>
      <div class="dropdown" id="dropdown-menu">
        <a href="homepage.html">Inicio</a>
        <a href="mnthclsr.html">Cierre de mes</a>
        <a href="admin.html">Menu de administador</a>
        <a href="about.html">Acerca de</a>
        <a href="help.html">Ayuda</a>
        <a href="login.html">Cerrar Sesion</a>
      </div>
    </div>
  </div>
</header>

<main class="menu">
  <div class="card">
    <img src="img/warehouse.png" alt="Almacén" />
    <a href="warehouse.html"><button class="card-btn">MI ALMACEN</button></a>
  </div>
  <div class="card">
    <img src="img/inventory-management.png" alt="Inventario" />
    <a href="inventory.html"><button class="card-btn">INVENTARIO</button></a>
  </div>
  <div class="card">
    <img src="img/documentation.png" alt="Reportes" />
    <a href="reports.html"><button class="card-btn">REPORTES</button></a>
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
