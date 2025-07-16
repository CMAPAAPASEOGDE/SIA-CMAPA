<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['idUsuario']) || empty($_SESSION['idUsuario'])) {
    header("Location: index.php");
    exit();
}
?>



<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Password Change</title>
    <link rel="stylesheet" href="css/StylePSCH.css">
</head>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const mensaje = document.getElementById('mensaje-resultado');

    document.getElementById('pwd-accept').addEventListener('click', () => {
      const oldPass = document.getElementById('old-pass').value.trim();
      const newPass = document.getElementById('new-pass').value.trim();
      const confirm = document.getElementById('confirm-pass').value.trim();

      mensaje.style.display = 'none';
      mensaje.className = 'mensaje';

      if (!oldPass || !newPass || !confirm) {
        mensaje.textContent = 'Completa todos los campos.';
        mensaje.classList.add('error');
        mensaje.style.display = 'block';
        return;
      }

      if (newPass !== confirm) {
        mensaje.textContent = 'Alguna de las contraseñas no coinciden. Favor de verificar los datos e intentarlo de nuevo.';
        mensaje.classList.add('error');
        mensaje.style.display = 'block';
        return;
      }

      // Llamada AJAX para enviar a PHP
      fetch('php/cambiar_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ actual: oldPass, nueva: newPass })
      })
      .then(res => res.json())
      .then(data => {
        mensaje.textContent = data.mensaje;
        mensaje.classList.add(data.exito ? 'ok' : 'error');
        mensaje.style.display = 'block';
      })
      .catch(() => {
        mensaje.textContent = 'Error inesperado. Intenta más tarde.';
        mensaje.classList.add('error');
        mensaje.style.display = 'block';
      });
    });
  });
</script>

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

<main class="pwd-container">
  <form id="form-pass">
    <div class="pwd-box">
        <!-- Contraseña anterior -->
        <div class="pwd-field">
            <img src="img/padlock.png" class="pwd-icon" alt="Lock">
            <input type="password" id="old-pass" name="old_Pass" placeholder="Contraseña Anterior" required>
        </div>
        <!-- Contraseña nueva -->
        <div class="pwd-field">
            <img src="img/padlock.png" class="pwd-icon" alt="Lock">
            <input type="password" id="new-pass" name="new_pass" placeholder="Contraseña Nueva" required>
        </div>
        <!-- Confirmar contraseña -->
        <div class="pwd-field">
            <img src="img/padlock.png" class="pwd-icon" alt="Lock">
            <input type="password" id="confirm-pass" name="confirm-pass" placeholder="Confirmar Contraseña" required>
        </div>
        <!-- Botón aceptar -->
        <button type="button" class="accept-btn" id="pwd-accept">ACEPTAR</button>
        <!-- Mensaje de resultado -->
        <p id="mensaje-resultado" class="mensaje"></p>
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
