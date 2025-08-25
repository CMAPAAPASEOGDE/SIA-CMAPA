<?php
session_start();

// Verificar sesión y rol
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1, 2])) {
    header("Location: acceso_denegado.php");
    exit();
}

// Conexión a la base de datos
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
    die("Error de conexión: " . print_r(sqlsrv_errors(), true));
}

// Inicializar variables
$operativos = [];
$proximoNumero = '0001';
$fechaActual = date('Y-m-d');

// Obtener operativos disponibles
$sqlOperativos = "SELECT idOperador, nombreCompleto FROM Operativo";
$stmtOperativos = sqlsrv_query($conn, $sqlOperativos);
if ($stmtOperativos) {
    while ($row = sqlsrv_fetch_array($stmtOperativos, SQLSRV_FETCH_ASSOC)) {
        $operativos[] = $row;
    }
}

// Obtener el próximo número de caja disponible
$sqlMaxCaja = "SELECT MAX(CAST(numeroCaja AS INT)) AS maxCaja FROM CajaRegistro WHERE numeroCaja <> '0000'";
$stmtMaxCaja = sqlsrv_query($conn, $sqlMaxCaja);
if ($stmtMaxCaja) {
    $row = sqlsrv_fetch_array($stmtMaxCaja, SQLSRV_FETCH_ASSOC);
    if ($row && $row['maxCaja'] !== null) {
        $proximoNumero = str_pad($row['maxCaja'] + 1, 4, '0', STR_PAD_LEFT);
    }
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idOperador = (int)($_POST['idOperador'] ?? 0);
    $autoriza = trim($_POST['autoriza'] ?? '');
    
    // Validar datos
    if ($idOperador <= 0) {
        $_SESSION['error'] = "Debe seleccionar un responsable operativo válido";
        header("Location: boxnwreger.php");
        exit();
    } 
    
    if (empty($autoriza)) {
        $_SESSION['error'] = "El campo 'Autoriza' es obligatorio";
        header("Location: boxnwreger.php");
        exit();
    }
    
    // Verificar si el operador ya tiene caja asignada
    $sqlCheck = "SELECT COUNT(*) AS total 
                 FROM CajaRegistro 
                 WHERE idOperador = ? 
                 AND numeroCaja <> '0000'";
    $paramsCheck = [$idOperador];
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);
    $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    
    if ($rowCheck && $rowCheck['total'] > 0) {
        $_SESSION['error'] = "El operador seleccionado ya tiene una caja asignada";
        header("Location: boxnwreger.php");
        exit();
    }
    
    // Insertar nueva caja
    $sqlInsert = "INSERT INTO CajaRegistro (numeroCaja, idOperador, fechaRegistro, autoriza) 
                  VALUES (?, ?, ?, ?)";
    $params = [$proximoNumero, $idOperador, $fechaActual, $autoriza];
    $stmtInsert = sqlsrv_query($conn, $sqlInsert, $params);
    
    if ($stmtInsert) {
        // Obtener ID de la nueva caja
        $sqlId = "SELECT SCOPE_IDENTITY() AS idCaja";
        $stmtId = sqlsrv_query($conn, $sqlId);
        $rowId = sqlsrv_fetch_array($stmtId, SQLSRV_FETCH_ASSOC);
        $idCaja = $rowId['idCaja'];
        
        // Redirigir a página de confirmación
        $_SESSION['nueva_caja'] = [
            'numeroCaja' => $proximoNumero,
            'fechaRegistro' => $fechaActual,
            'autoriza' => $autoriza
        ];
        header("Location: boxnwregcnf.php?idCaja=$idCaja");
        exit();
    } else {
        $_SESSION['error'] = "Error al registrar la caja: " . print_r(sqlsrv_errors(), true);
        header("Location: boxnwreger.php");
        exit();
    }
}

?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Toolbox New Register</title>
    <link rel="stylesheet" href="css/StyleBXNR.css">
    <style>
        .error-message {
            color: #ff0000;
            background-color: #ffecec;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }
    </style>
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

<main class="caja-registro-container">
    <div class="caja-registro-title">
        <h2>CAJAS</h2>
        <p class="subtitulo">REGISTRO NUEVO</p>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST" class="caja-registro-form">
        <label for="idOperador">RESPONSABLE OPERATIVO</label>
        <select id="idOperador" name="idOperador" required>
            <option value="">Seleccionar responsable</option>
            <?php foreach ($operativos as $op): ?>
                <option value="<?= $op['idOperador'] ?>"><?= htmlspecialchars($op['nombreCompleto']) ?></option>
            <?php endforeach; ?>
        </select>
        
        <label for="fecha">FECHA DE REGISTRO</label>
        <input type="date" id="fecha" name="fecha" value="<?= $fechaActual ?>" readonly>
        
        <label for="caja">ASIGNACIÓN DE CAJA</label>
        <input type="text" id="caja" name="caja" value="<?= $proximoNumero ?>" readonly>
        
        <label for="autoriza">AUTORIZA</label>
        <input type="text" id="autoriza" name="autoriza" required>
        
        <div class="registro-actions">
            <a href="boxes.php"><button type="button" class="btn">CANCELAR</button></a>
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
