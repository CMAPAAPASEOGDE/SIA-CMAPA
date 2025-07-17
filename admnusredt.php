<?php
session_start();

if (!isset($_SESSION['editar_usuario'])) {
    header("Location: admnusredsrch.php");
    exit();
}

$user = $_SESSION['editar_usuario'];

// Verificar permisos
if (!isset($_SESSION['user_id']) || (int)$_SESSION['rol'] !== 1) {
    header("Location: index.php");
    exit();
}

// Manejar errores de actualización
$error = '';
if (isset($_SESSION['error_actualizacion'])) {
    $error = $_SESSION['error_actualizacion'];
    unset($_SESSION['error_actualizacion']);
}
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Admin User Edit</title>
    <link rel="stylesheet" href="css/StyleADUSED.css">
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

<main class="main-editar-usuario">
  <div class="contenedor-titulo">
    <h2>EDITAR USUARIO</h2>
  </div>

  <?php if (!empty($error)): ?>
    <div class="error-message"><?= $error ?></div>
  <?php endif; ?>

  <form action="php/actualizar_usuario.php" method="POST">
    <input type="hidden" name="idUsuario" value="<?= $user['idUsuario'] ?>">
    <div class="formulario-usuario">
        <div class="fila">
            <div class="campo">
                <label for="usuario">USUARIO</label>
                <input type="text" id="usuario" name="usuario" value="<?= htmlspecialchars($user['usuario']) ?>" readonly />
            </div>
            <div class="campo">
                <label for="tipo">TIPO DE USUARIO</label>
                <select id="tipo" name="rol">
                  <option value="1" <?= $user['idRol'] == 1 ? 'selected' : '' ?>>Administrador</option>
                  <option value="2" <?= $user['idRol'] == 2 ? 'selected' : '' ?>>Almacenista</option>
                  <option value="3" <?= $user['idRol'] == 3 ? 'selected' : '' ?>>Observador</option>
                </select>
            </div>
        </div>
        <div class="fila">
            <div class="campo-check">
                <label for="cambiar-contra">CONTRASEÑA</label>
                <div class="grupo-check">
                    <input type="checkbox" id="cambiar-contra" name="cambiar_contra" value="1" />
                    <input type="password" name="password" placeholder="Nueva contraseña" disabled />
                </div>
            </div>
            <div class="campo-check">
                <label for="confirmar">CONFIRMAR CONTRASEÑA</label>
                <div class="grupo-check">
                    <input type="password" name="confirm_password" placeholder="Confirmar nueva contraseña" disabled />
                </div>
            </div>
        </div>
        <div class="fila">
            <div class="campo">
                <label for="apodo">APODO</label>
                <input type="text" id="apodo" name="apodo" value="<?= htmlspecialchars($user['apodo']) ?>" />
            </div>
            <div class="campo-estatus">
                <label>ESTATUS</label>
                <div class="estatus-checks">
                    <label><input type="radio" name="estatus" value="0" <?= $user['estatus'] == 0 ? 'checked' : '' ?> /> INACTIVO</label>
                    <label><input type="radio" name="estatus" value="1" <?= $user['estatus'] == 1 ? 'checked' : '' ?> /> ACTIVO</label>
                </div>
            </div>
        </div>
        
          <div class="botones">
              <a href="admnusrs.php"><button type="button" class="btn-cancelar">CANCELAR</button></a>
              <button type="submit" class="btn-confirmar">CONFIRMAR</button>
          </div>
    </div>
  </form>
</main>

<script>
  // Habilitar campos de contraseña cuando se marca el checkbox
  const checkboxContra = document.getElementById('cambiar-contra');
  const inputsContra = document.querySelectorAll('input[type="password"]');
  
  checkboxContra.addEventListener('change', () => {
    inputsContra.forEach(input => {
        input.disabled = !checkboxContra.checked;
        if (!checkboxContra.checked) input.value = '';
    });
  });
</script

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
