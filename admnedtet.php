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
if ($idRol !== 1) {
    header("Location: acceso_denegado.php");
    exit();
}
?>

<?php
  // Obtener fecha actual en formato DD/MM/AAAA
  $fecha_actual = date('d/m/Y');
?>


<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Admin Elements Edition Entry</title>
    <link rel="stylesheet" href="css/StyleADEDET.css">
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

<main class="main-container">
    <h1 class="titulo-seccion">EDITAR ELEMENTOS</h1>
    <div class="contenedor-formulario">
        <h2 class="subtitulo">AÑADIR ELEMENTOS</h2>
        <label for="codigo">CÓDIGO</label>
        <select id="codigo" class="input-ancho-grande">
            <option>ABCDEFGHIJKLMNÑOPQRSTUVWXYZ0123456789</option>
        </select>
        <div class="grupo-campos">
            <div>
                <label for="nombre">NOMBRE</label>
                <input type="text" id="nombre" placeholder="Escriba Aqui">
            </div>
            <div>
                <label for="linea">LÍNEA</label>
                <input type="text" id="linea" placeholder="Escriba Aqui">
            </div>
            <div>
                <label for="sublinea">SUBLÍNEA</label>
                <input type="text" id="sublinea" placeholder="Escriba Aqui">
            </div>
        </div>
        <div class="grupo-campos">
            <div>
                <label for="proveedor">PROVEEDOR</label>
                <select id="proveedor">
                    <option>Ferremaquinas</option>
                </select>
            </div>
            <div>
                <label for="cantidad">CANTIDAD</label>
                <input type="number" id="cantidad" value="0">
            </div>
            <div>
                <label for="fecha">FECHA DE REGISTRO</label>
                <input type="date" id="fecha" value="<?php echo $fecha_actual; ?>">
            </div>
        </div>
        <div class="botones-formulario">
            <a href="admnedt.php"><button class="boton-negro">CANCELAR</button></a>
            <button class="boton-negro">CONFIRMAR</button>
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
</body>
</html>
