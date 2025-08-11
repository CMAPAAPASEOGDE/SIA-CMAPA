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
?>

<!DOCTYPE html>

<html>
<head>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <meta charset="UTF-8" />
    <title>SIA CONFIRMATION</title>
    <link rel="stylesheet" href="css/StyleDVTLCF.css">
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

<article class="ft-container">
  <div class="son-container">
    <h2>CONFIRMACION</h2>
    <p>
      HERRAMIENTA DEVUELTA EXITOSAMENTE
    </p>
    <div class="form-buttons">
      <a href="warehouse.php"><button type="submit" class="btn confirm">CONFIRMAR</button></a>
    </div>
  </div>
</article>

<main class="devolucion-container">
    <div class="devolucion-title">
        <h2>DEVOLUCIÓN DE HERRAMIENTAS</h2>
    </div>
    
    <form class="devolucion-form">
        <!-- Código -->
        <label for="codigo">CÓDIGO</label>
        <select id="codigo">
            <option selected>ABCDEFGHIJKLMNÑOPQRSTUVWXYZ0123456789</option>
            <!-- Agrega más códigos reales aquí -->
        </select>
        <!-- Nombre -->
        <label for="nombre">NOMBRE O DESCRIPCIÓN</label>
        <input type="text" id="nombre" value="TALADRO MAKITA">
        <!-- Observaciones -->
        <label for="observaciones">OBSERVACIONES</label>
        <textarea id="observaciones" rows="4">EL TALADRO SE ENTREGA CON 2 BROCAS FALTANTES, UNA BATERIA FALTANTE, YA NO SUJETA BIEN LA BATERIA TIENEN QUE SUJETARLA CON CINTA</textarea>
        <!-- Estado y Fecha -->
        <div class="form-row">
            <div class="form-group">
                <label for="estado">ESTADO</label>
                <select id="estado">
                <option>NECESITA CAMBIO</option>
                <option>FUNCIONAL</option>
                <option>EN REPARACIÓN</option>
                </select>
            </div>
            <div class="form-group">
                <label for="fecha">FECHA DE RETORNO</label>
                <input type="date" id="fecha" value="2025-04-30">
            </div>
        </div>
        <!-- Botones -->
        <div class="form-buttons">
            <button type="button" class="btn cancel">CANCELAR</button>
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
</body>
</html>
