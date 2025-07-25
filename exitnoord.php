<?php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1, 2])) {
    header("Location: acceso_denegado.php");
    exit();
}

// Obtener productos desde la BD
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) die(print_r(sqlsrv_errors(), true));

$productos = [];
$queryProd = "SELECT idCodigo, codigo, descripcion FROM Productos";
$stmt = sqlsrv_query($conn, $queryProd);
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $productos[] = $row;
}
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Exit Without Order</title>
    <link rel="stylesheet" href="css/StyleETNOOD.css">
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

<main class="salida-container">
    <h2 class="salida-title">SALIDAS</h2>
    <div class="salida-tabs">
        <a href="exitord.php"><button class="tab new-bttn">SALIDA CON ORDEN</button></a>
        <button class="tab activo">SALIDA SIN ORDEN (USO INTERNO)</button>
    </div>
    <form class="salida-form" action="php/registrar_salida_noorden.php" method="POST">
        <div class="salida-row">
            <div class="salida-col">
                <label>ÁREA QUE SOLICITA</label>
                <input type="text" name="areaSolicitante" required />
            </div>
            <div class="salida-col">
                <label>QUIÉN SOLICITA</label>
                <input type="text" name="encargadoArea" required />
            </div>
            <div class="salida-col">
                <label>FECHA</label>
                <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" readonly />
            </div>
        </div>
        <div class="salida-row">
            <div class="salida-col full">
                <label>COMENTARIOS</label>
                <textarea name="comentarios" rows="5" required></textarea>
            </div>
        </div>

        <div class="salida-items" id="salida-items">
            <h3 class="items-title">ELEMENTOS</h3>
            <div class="salida-row item">
                <select name="elementos[0][idCodigo]" class="codigo-select" onchange="cargarNombre(this)">
                    <option value="">Seleccionar código</option>
                    <?php foreach ($productos as $prod): ?>
                        <option value="<?= $prod['idCodigo'] ?>" data-nombre="<?= htmlspecialchars($prod['descripcion']) ?>">
                            <?= htmlspecialchars($prod['codigo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="elementos[0][nombre]" placeholder="NOMBRE" readonly />
                <input type="number" name="elementos[0][cantidad]" placeholder="CANTIDAD" min="1" required />
            </div>
        </div>

        <div class="salida-actions">
            <a href="warehouse.php"><button type="button" class="btn cancel">CANCELAR</button></a>
            <button type="button" class="btn add" onclick="agregarElemento()">AÑADIR ELEMENTOS</button>
            <button type="submit" class="btn confirm">CONFIRMAR SALIDA</button>
        </div>
    </form>
</main>

<script>
let contador = 1;
const productos = <?= json_encode($productos) ?>;

function cargarNombre(select) {
    const nombreInput = select.nextElementSibling;
    const selectedOption = select.options[select.selectedIndex];
    nombreInput.value = selectedOption.getAttribute('data-nombre') || "";
}

function agregarElemento() {
    const container = document.getElementById('salida-items');
    const nuevo = document.createElement('div');
    nuevo.className = "salida-row item";
    nuevo.innerHTML = `
        <select name="elementos[${contador}][idCodigo]" class="codigo-select" onchange="cargarNombre(this)">
            <option value="">Seleccionar código</option>
            ${productos.map(p => `<option value="${p.idCodigo}" data-nombre="${p.descripcion}">${p.codigo}</option>`).join('')}
        </select>
        <input type="text" name="elementos[${contador}][nombre]" placeholder="NOMBRE" readonly />
        <input type="number" name="elementos[${contador}][cantidad]" placeholder="CANTIDAD" min="1" required />
    `;
    container.appendChild(nuevo);
    contador++;
}
</script>

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
