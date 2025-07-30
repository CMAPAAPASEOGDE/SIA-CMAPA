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
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA CONFIRMATION</title>
    <link rel="stylesheet" href="css/StyleBXIPER.css">
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
      MODIFICACIÓN REALIZADA DE FORMA EXITOSA.
    </p>
    <div class="form-buttons">
      <a href="boxes.html"><button type="submit" class="btn confirm">CONFIRMAR</button></a>
    </div>
  </div>
</article>

<main class="caja-gestion-container">
    <div class="caja-gestion-title">
        <h2>CAJAS</h2>
        <div class="caja-numero">CAJA 001</div>
    </div>
    <section class="responsable-section">
        <label for="responsable">RESPONSABLE</label>
        <input type="text" id="responsable" value="ABCDE FGHIJKL MIÑO PQR STUVWXYZ">
        <button class="btn-secundario">CAMBIAR RESPONSABLE</button>
    </section>
    <section class="elementos-section">
        <div class="elementos-header">
            <span>CÓDIGO</span>
            <span>CONTENIDO</span>
            <span>CANTIDAD</span>
        </div>
        <div class="elemento-row">
            <input type="text" value="ABCDEFGHIJKLMNÑOPQRSTUVWXYZ">
            <input type="text" value="MEDIDOR DE AGUA">
            <div class="cantidad-control">
                <input type="number" value="1" min="0">
            </div>
        </div>
        <div class="elemento-row">
            <input type="text" value="ABCDEFGHIJKLMNÑOPQRSTUVWXYZ">
            <input type="text" value="Llave de presión">
            <div class="cantidad-control">
                <input type="number" value="1" min="0">
            </div>
        </div>
        <div class="elemento-row">
            <input type="text" value="ABCDEFGHIJKLMNÑOPQRSTUVWXYZ">
            <input type="text" value="Codos de cobre">
            <div class="cantidad-control">
                <input type="number" value="3" min="0">
            </div>
        </div>
    </section>
    <div class="caja-gestion-actions">
        <button class="btn-secundario">AÑADIR NUEVO ELEMENTO</button>
        <button class="btn-secundario">BORRAR LA CAJA</button>
        <button class="btn">CANCELAR</button>
        <button class="btn">CONFIRMAR</button>
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
