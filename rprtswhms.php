<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Si no hay sesión activa, redirigir al login
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/php/reportes_whms_utils.php';
$conn = db_conn_or_die();
$productos = get_product_catalog($conn);
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Reports Warehouse Movements</title>
    <link rel="stylesheet" href="css/StyleRPWHMS.css">
</head>

<body>
<header>
  <div class="brand">
    <img src="img/cmapa.png" class="logo" />
    <h1>SIA - CMAPA</h1>
  </div>
  <div class="header-right">
    <p> <?= $_SESSION['usuario'] ?> </p>
    <div class="user-menu-container">
      <button class="icon-btn" id="user-toggle">
        <img src="img/userB.png" class="imgh2" alt="Usuario" />
      </button>
      <div class="user-dropdown" id="user-dropdown">
        <p><strong>Tipo de Usuario:</strong> <?= $_SESSION[ 'rol' ]?></p>
        <p><strong>Apodo:</strong> <?= htmlspecialchars($_SESSION['nombre'])?></p>
        <a href="passchng.php"><button class="user-option">CAMBIAR CONTRASEÑA</button></a>
      </div>
    </div>
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

<main class="reportes-container">
  <h2 class="reportes-title">Reportes</h2>
  <h3 class="reportes-subtitle">MOVIMIENTOS DEL ALMACÉN</h3>
  <p class="reportes-filtro">FILTRAR POR</p>

  <form class="reporte-filtros" method="post" action="php/generar_whms.php">
    <div class="form-grid grid-3">
      <label>CÓDIGO
        <select name="idCodigo">
          <option value="">-- TODOS --</option>
          <?php foreach ($productos as $p): ?>
            <option value="<?= (int)$p['idCodigo'] ?>">
              <?= htmlspecialchars(($p['codigo'] ?? '') . ' — ' . ($p['descripcion'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>MES
        <select name="mes" required>
          <?php
          $meses = ['01'=>'ENERO','02'=>'FEBRERO','03'=>'MARZO','04'=>'ABRIL','05'=>'MAYO','06'=>'JUNIO','07'=>'JULIO','08'=>'AGOSTO','09'=>'SEPTIEMBRE','10'=>'OCTUBRE','11'=>'NOVIEMBRE','12'=>'DICIEMBRE'];
          $mesActual = date('m');
          foreach ($meses as $k=>$v) {
              $sel = ($k === $mesActual) ? 'selected' : '';
              echo "<option value=\"$k\" $sel>$v</option>";
          }
          ?>
        </select>
      </label>

      <label>AÑO
        <select name="anio" required>
          <?php
          $y = (int)date('Y');
          for ($yy = $y; $yy >= $y-5; $yy--) {
              echo "<option value=\"$yy\">$yy</option>";
          }
          ?>
        </select>
      </label>
    </div>

    <div class="report-buttons">
      <a href="reports.php"><button type="button">CANCELAR</button></a>
      <button type="submit" formaction="php/exportar_whms_pdf.php" name="action" value="pdf">GENERAR PDF</button>
      <button type="submit" formaction="php/exportar_whms_excel.php" name="action" value="xlsx">GENERAR XLSX</button>
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
