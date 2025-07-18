<!DOCTYPE html>

<html>
<head>
    <title>SIA CONFIRMATION</title>
    <link rel="stylesheet" href="css/StyleADEDEDCF.css">
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
        <p><u>Tu solicitud de cambio de inventario se ha realizado exitosamente.</u></p>
        <p>Producto: <strong>"Tornillos 1/4"</strong> llegó al punto de reorden.</p>
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
        <button class="user-option">CAMBIAR CONTRASEÑA</button>
      </div>
    </div>
    <!-- botón hamburguesa -->
    <div class="menu-container">
      <button class="icon-btn" id="menu-toggle">
        <img src="img/menu.png" alt="Menú" />
      </button>
      <div class="dropdown" id="dropdown-menu">
        <a href="#">Inicio</a>
        <a href="#">Cierre de mes</a>
        <a href="#">Menu de administador</a>
        <a href="#">Acerca de</a>
        <a href="#">Ayuda</a>
        <a href="#">Cerrar Sesion</a>
      </div>
    </div>
  </div>
</header>

<article class="ft-container">
  <div class="son-container">
    <h2>CONFIRMACIÓN</h2>
    <p>
      LOS DETALLES DEL ELEMENTO SE HAN EDITADO CORRECTAMENTE.
    </p>
    <div class="form-buttons">
      <a href="admnedt.html"><button type="submit" class="btn confirm">CONFIRMAR</button></a>
    </div>
  </div>
</article>

<main class="main-container">
    <!-- Título general -->
    <h1 class="titulo-seccion">EDITAR ELEMENTOS</h1>
    <!-- Panel gris -->
    <section class="contenedor-formulario">
        <h2 class="subtitulo">EDITAR DETALLES DE LOS ELEMENTOS</h2>
        <form class="grid-form">
            <!-- Fila 1 -->
            <label>CÓDIGO
                <input type="text" value="ABCDEFGHIJKLMNÑOPQRSTUVWXYZ0123456789" />
            </label>
            <label>TIPO
                <select>
                    <option>Herramienta</option>
                    <option>Material</option>
                </select>
            </label>
            <!-- Fila 2 -->
            <label>NOMBRE
                <input type="text" value="ROTOMARTILLO MILWAUKEE" />
            </label>
            <label>LÍNEA
                <input type="text" value="ABCDEFGHIJKLM" />
            </label>
            <label>SUBLÍNEA
                <input type="text" value="ABCDEFGHIJKLM" />
            </label>
            <!-- Fila 3 -->
            <label>PROVEEDOR
                <select>
                    <option>Ferremaquinas</option>
                    <option>Otro</option>
                </select>
            </label>
            <label>PRECIO
                <input type="number" step="0.01" value="9500.00" />
            </label>
            <label>ESTATUS
                <select>
                    <option>Fuera del almacén</option>
                    <option>Punto de reorden</option>
                    <option>En stock</option>
                </select>
            </label>
            <!-- Fila 4 -->
            <label>UNIDAD
                <select>
                    <option>Piezas</option>
                    <option>Kg</option>
                    <option>M</option>
                </select>
            </label>
            <label>PUNTO REORDEN
                <input type="number" value="1" />
            </label>
                <label>STOCK MÁXIMO
                <input type="number" value="3" />
            </label>
        </form>
        <!-- Botones -->
        <div class="botones-formulario">
            <button class="boton-negro">CANCELAR</button>
            <button class="boton-negro">CONFIRMAR</button>
        </div>
    </section>
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
