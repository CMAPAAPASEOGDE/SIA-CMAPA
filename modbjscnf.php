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
    <title>SIA CONFIRMATION</title>
    <link rel="stylesheet" href="css/StyleMDBJCF.css">
</head>

<body>
<header>
  <div class="brand">
    <img src="img/cmapa.png" class="logo" />
    <h1>SIA - CMAPA</h1>
  </div>
  <div class="header-right">
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
      TU SOLICITUD SE HA ENVIADO CORRECTAMENTE. <br>
      RECUERDA ESTAR AL PENDIENTE DE LAS NOTIFICACIONES PARA CONOCER EL ESTATUS DE TU SOLICITUD.
    </p>
    <div class="form-buttons">
      <a href="modif.php"><button type="submit" class="btn confirm">CONFIRMAR</button></a>
    </div>
  </div>
</article>

<main class="altas-container">
    <div class="altas-title">
        <h2>MODIFICACIONES DE INVENTARIO</h2>
        <h3>BAJAS</h3>
    </div>
    <form class="altas-form">
        <label for="codigo">CÓDIGO</label>
        <input type="text" id="codigo" name="codigo" value="">
        <label for="motivo">MOTIVO DE LA ALTA</label>
        <textarea id="motivo" name="motivo" rows="4"></textarea>
        <div class="altas-row">
            <div class="altas-column">
                <label for="cantidad">CANTIDAD</label>
                <input type="number" id="cantidad" name="cantidad" value="">
            </div>
            <div class="altas-column">
                <label for="fecha">FECHA DE SOLICITUD</label>
                <input type="date" id="fecha" name="fecha" value="">
            </div>
        </div>
        <div class="altas-buttons">
            <button type="button" class="btn">CANCELAR</button>
            <button type="submit" class="btn">CONFIRMAR</button>
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
