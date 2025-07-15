<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Si no hay sesión activa, redirigir al login
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Inventory</title>
    <link rel="stylesheet" href="css/StyleINV.css">
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

<main class="inventory-container">
    <h2 class="inventory-title">INVENTARIO</h2>
    <div class="inventory-table-wrapper">
        <table class="inventory-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Línea</th>
                    <th>Sublínea</th>
                    <th>Cantidad</th>
                    <th>Unidad</th>
                    <th>Precio</th>
                    <th>Punto de Reorden</th>
                    <th>Stock maximo</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1230000001</td>
                    <td>Tornillos 1/4</td>
                    <td>XXXXXXXX</td>
                    <td>XXXXXXXX</td>
                    <td>50</td>
                    <td>Piezas</td>
                    <td>$5.00</td>
                    <td>10</td>
                    <td>50</td>
                    <td>Material</td>
                    <td><span class="estado green">En stock</span></td>
                </tr>
                <tr>
                    <td>1230000002</td>
                    <td>Taladro Makita</td>
                    <td>XXXXXXXX</td>
                    <td>XXXXXXXX</td>
                    <td>1</td>
                    <td>Piezas</td>
                    <td>$545.00</td>
                    <td>1</td>
                    <td>5</td>
                    <td>Herramienta</td>
                    <td><span class="estado green">En Almacén</span></td>
                </tr>
                <tr>
                    <td>1230000003</td>
                    <td>Cinchos varios</td>
                    <td>XXXXXXXX</td>
                    <td>XXXXXXXX</td>
                    <td>0</td>
                    <td>Piezas</td>
                    <td>******</td>
                    <td>5</td>
                    <td>60</td>
                    <td>Material</td>
                    <td><span class="estado red">Fuera de stock</span></td>
                </tr>
                <tr>
                    <td>1230000004</td>
                    <td>Bulto Cal 20KG</td>
                    <td>XXXXXXXX</td>
                    <td>XXXXXXXX</td>
                    <td>50</td>
                    <td>Piezas</td>
                    <td>******</td>
                    <td>5</td>
                    <td>40</td>
                    <td>Material</td>
                    <td><span class="estado green-bright">SOBRE STOCK</span></td>
                </tr>
                <tr>
                    <td>1230000005</td>
                    <td>Generador de Gasolina B-Power</td>
                    <td>XXXXXXXX</td>
                    <td>XXXXXXXX</td>
                    <td>1</td>
                    <td>Piezas</td>
                    <td>******</td>
                    <td>0</td>
                    <td>1</td>
                    <td>Herramienta</td>
                    <td><span class="estado yellow">FUERA DE ALMACEN</span></td>
                </tr>
                <!-- Más registros... -->
            </tbody>
        </table>
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
