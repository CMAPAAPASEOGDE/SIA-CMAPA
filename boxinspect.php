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

// Obtener el ID de la caja desde la URL
$idCaja = isset($_GET['idCaja']) ? intval($_GET['idCaja']) : 0;
if ($idCaja <= 0) {
    header("Location: boxes.php");
    exit();
}

// Conectar a la BD
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Obtener el nombre del operador y número de caja
$sqlCaja = "SELECT C.numeroCaja, O.nombre AS nombreOperador 
            FROM CajaRegistro C
            INNER JOIN Operativo O ON C.idOperador = O.idOperador
            WHERE C.idCaja = ?";
$stmtCaja = sqlsrv_query($conn, $sqlCaja, [$idCaja]);
$datosCaja = sqlsrv_fetch_array($stmtCaja, SQLSRV_FETCH_ASSOC);
$numeroCaja = $datosCaja['numeroCaja'] ?? '---';
$nombreOperador = $datosCaja['nombreOperador'] ?? 'SIN OPERADOR';

// Obtener contenido de la caja
$sqlContenido = "SELECT cc.idCodigo, p.nombre AS descripcion, cc.cantidad
                 FROM CajaContenido cc
                 INNER JOIN Productos p ON cc.idCodigo = p.idCodigo
                 WHERE cc.idCaja = ?";
$stmtContenido = sqlsrv_query($conn, $sqlContenido, [$idCaja]);
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Toolbox Inspection</title>
    <link rel="stylesheet" href="css/StyleBXIP.css">
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

<main class="caja-gestion-container">
    <div class="caja-gestion-title">
        <h2>CAJA</h2>
        <div class="caja-numero">CAJA <?= htmlspecialchars($numeroCaja) ?></div>
    </div>
    <section class="responsable-section">
        <label for="responsable">RESPONSABLE</label>
        <input type="text" id="responsable" value="<?= htmlspecialchars($nombreOperador) ?>" readonly>
        <?php if ($idRol === 1): ?>
            <a href="cambiar_responsable.php?idCaja=<?= $idCaja ?>"><button class="btn-secundario">CAMBIAR RESPONSABLE</button></a>
        <?php endif; ?>
    </section>

    <section class="elementos-section">
        <div class="elementos-header">
            <span>CÓDIGO</span>
            <span>CONTENIDO</span>
            <span>CANTIDAD</span>
        </div>
        <?php while ($row = sqlsrv_fetch_array($stmtContenido, SQLSRV_FETCH_ASSOC)): ?>
            <div class="elemento-row">
                <input type="text" value="<?= htmlspecialchars($row['idCodigo']) ?>" readonly>
                <input type="text" value="<?= htmlspecialchars($row['descripcion']) ?>" readonly>
                <div class="cantidad-control">
                    <input type="number" value="<?= htmlspecialchars($row['cantidad']) ?>" readonly>
                </div>
            </div>
        <?php endwhile; ?>
    </section>

    <div class="caja-gestion-actions">
        <a href="añadir_elemento_caja.php?idCaja=<?= $idCaja ?>"><button class="btn-secundario">AÑADIR NUEVO ELEMENTO</button></a>
        <?php if ($idRol === 1): ?>
            <a href="eliminar_caja.php?idCaja=<?= $idCaja ?>"><button class="btn-secundario">BORRAR LA CAJA</button></a>
        <?php endif; ?>
        <a href="boxes.php"><button class="btn">CANCELAR</button></a>
        <a href="#"><button class="btn">CONFIRMAR</button></a>
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
