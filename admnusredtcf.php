<?php
// Iniciar sesión
session_start();

if (!isset($_SESSION['editar_usuario'])) {
    header("Location: admnusredsrch.php");
    exit();
}

$user = $_SESSION['editar_usuario'];

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Si no hay sesión activa, redirigir al login
    header("Location: index.php");
    exit();
}

// Verificar el rol del usuario
$idRol = (int)($_SESSION['rol'] ?? 0);
if ($idRol !== 1) {
    header("Location: acceso_denegado.php");
    exit();
}
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA CONFIRMATION</title>
    <link rel="stylesheet" href="css/StyleADUSEDCF.css">
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
    <h2>CONFIRMACIÓN</h2>
    <p>
      DETALLES DE USUARIO EDITADOS CORRECTAMENTE.
    </p>
    <div class="form-buttons">
      <a href="admin.php"><button type="submit" class="btn confirm">CONFIRMAR</button></a>
    </div>
  </div>
</article>

<main class="main-editar-usuario">
    <div class="contenedor-titulo">
        <h2>EDITAR USUARIO</h2>
    </div>
    <div class="formulario-usuario">
        <div class="fila">
            <div class="campo">
                <label for="usuario">USUARIO</label>
                <input type="text" id="usuario" value="" />
            </div>
            <div class="campo">
                <label for="tipo">TIPO DE USUARIO</label>
                <select id="tipo">
                    <option selected>Observador</option>
                    <option>Administrador</option>
                </select>
            </div>
        </div>
        <div class="fila">
            <div class="campo-check">
                <label for="cambiar-contra">CONTRASEÑA</label>
                <div class="grupo-check">
                    <input type="checkbox" id="cambiar-contra" />
                    <input type="password" placeholder="" disabled />
                </div>
            </div>
            <div class="campo-check">
                <label for="confirmar">CONFIRMAR CONTRASEÑA</label>
                <div class="grupo-check">
                    <input type="checkbox" disabled />
                    <input type="password" placeholder="" disabled />
                </div>
            </div>
        </div>
        <div class="fila">
            <div class="campo">
                <label for="apodo">APODO</label>
                <input type="text" id="apodo" value="" />
            </div>
            <div class="campo-estatus">
                <label>ESTATUS</label>
                <div class="estatus-checks">
                    <label><input type="checkbox" /> INACTIVO</label>
                    <label><input type="checkbox" checked /> ACTIVO</label>
                </div>
            </div>
        </div>
        <div class="botones">
            <button class="btn-cancelar">CANCELAR</button>
            <button class="btn-confirmar">CONFIRMAR</button>
        </div>
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

<script>
  const checkboxContra = document.getElementById('cambiar-contra');
  const inputContra = checkboxContra.nextElementSibling;
  const inputConfirm = document.querySelectorAll('.grupo-check input[type="password"]')[1];
  const confirmCheckbox = inputConfirm.previousElementSibling;

  checkboxContra.addEventListener('change', () => {
    if (checkboxContra.checked) {
      inputContra.disabled = false;
      inputConfirm.disabled = false;
      confirmCheckbox.disabled = false;
    } else {
      inputContra.disabled = true;
      inputConfirm.disabled = true;
      confirmCheckbox.disabled = true;
    }
  });
</script>
</body>
</html>
