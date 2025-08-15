<?php
session_start();

// Verificar sesión y rol
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1, 2], true)) {
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
$operativos     = [];
$proximoNumero  = '0001';
$fechaActual    = date('Y-m-d');

// Obtener operativos disponibles
$sqlOperativos = "SELECT idOperador, nombreCompleto FROM Operativo";
$stmtOperativos = sqlsrv_query($conn, $sqlOperativos);
if ($stmtOperativos) {
    while ($row = sqlsrv_fetch_array($stmtOperativos, SQLSRV_FETCH_ASSOC)) {
        $operativos[] = $row;
    }
    sqlsrv_free_stmt($stmtOperativos);
}

// Obtener el próximo número de caja disponible (excluye '0000')
$sqlMaxCaja = "SELECT MAX(TRY_CONVERT(INT, numeroCaja)) AS maxCaja FROM CajaRegistro WHERE numeroCaja <> '0000'";
$stmtMaxCaja = sqlsrv_query($conn, $sqlMaxCaja);
if ($stmtMaxCaja) {
    $row = sqlsrv_fetch_array($stmtMaxCaja, SQLSRV_FETCH_ASSOC);
    if ($row && $row['maxCaja'] !== null) {
        $proximoNumero = str_pad(((int)$row['maxCaja']) + 1, 4, '0', STR_PAD_LEFT);
    }
    sqlsrv_free_stmt($stmtMaxCaja);
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idOperador = (int)($_POST['idOperador'] ?? 0);
    $autoriza   = trim($_POST['autoriza'] ?? '');

    // Validar datos
    if ($idOperador <= 0) {
        $_SESSION['error'] = "Debe seleccionar un responsable operativo válido";
        header("Location: boxnewregister.php");
        exit();
    }
    if ($autoriza === '') {
        $_SESSION['error'] = "El campo 'Autoriza' es obligatorio";
        header("Location: boxnewregister.php");
        exit();
    }

    // Verificar si el operador ya tiene caja asignada (distinta de 0000)
    $sqlCheck = "SELECT COUNT(*) AS total 
                 FROM CajaRegistro 
                 WHERE idOperador = ? 
                   AND numeroCaja <> '0000'";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$idOperador]);
    $rowCheck  = $stmtCheck ? sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC) : null;
    if ($stmtCheck) sqlsrv_free_stmt($stmtCheck);

    if ($rowCheck && (int)$rowCheck['total'] > 0) {
        $_SESSION['error'] = "El operador seleccionado ya tiene una caja asignada";
        header("Location: boxnewregister.php");
        exit();
    }

    // Insertar nueva caja
    $sqlInsert = "INSERT INTO CajaRegistro (numeroCaja, idOperador, fechaRegistro, autoriza) 
                  VALUES (?, ?, ?, ?)";
    $params    = [$proximoNumero, $idOperador, $fechaActual, $autoriza];
    $stmtInsert = sqlsrv_query($conn, $sqlInsert, $params);

    if ($stmtInsert) {
        // Obtener ID de la nueva caja
        $stmtId = sqlsrv_query($conn, "SELECT SCOPE_IDENTITY() AS idCaja");
        $rowId  = $stmtId ? sqlsrv_fetch_array($stmtId, SQLSRV_FETCH_ASSOC) : null;
        if ($stmtId) sqlsrv_free_stmt($stmtId);

        $idCaja = $rowId ? (int)$rowId['idCaja'] : 0;

        // Redirigir a página de confirmación
        $_SESSION['nueva_caja'] = [
            'numeroCaja'    => $proximoNumero,
            'fechaRegistro' => $fechaActual,
            'autoriza'      => $autoriza
        ];
        header("Location: boxnwregcnf.php?idCaja={$idCaja}");
        exit();
    } else {
        $_SESSION['error'] = "Error al registrar la caja: " . print_r(sqlsrv_errors(), true);
        header("Location: boxnewregister.php");
        exit();
    }
}

/* =========================
   NOTIFICACIONES (header)
   ========================= */
$rolActual   = (int)($_SESSION['rol'] ?? 0);
$notifTarget = ($rolActual === 1) ? 'admnrqst.php' : 'mis_notifs.php';

$unreadCount = 0;
$notifList   = [];

if ($rolActual === 1) {
    // ADMIN: ver SOLO las destinadas a admin (idRol = 1)
    $stmtCount = sqlsrv_query($conn, "SELECT COUNT(*) AS c FROM Notificaciones WHERE solicitudRevisada = 0 AND idRol = 1");
    $stmtList  = sqlsrv_query($conn, "SELECT TOP 10 idNotificacion, descripcion, fecha
                                      FROM Notificaciones
                                      WHERE solicitudRevisada = 0 AND idRol = 1
                                      ORDER BY fecha DESC");
} else {
    // USUARIO: ver SOLO las destinadas a su rol
    $stmtCount = sqlsrv_query($conn, "SELECT COUNT(*) AS c FROM Notificaciones WHERE solicitudRevisada = 0 AND idRol = ?", [$rolActual]);
    $stmtList  = sqlsrv_query($conn, "SELECT TOP 10 idNotificacion, descripcion, fecha
                                      FROM Notificaciones
                                      WHERE solicitudRevisada = 0 AND idRol = ?
                                      ORDER BY fecha DESC", [$rolActual]);
}
if ($stmtCount) {
    $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
    $unreadCount = (int)($row['c'] ?? 0);
    sqlsrv_free_stmt($stmtCount);
}
if ($stmtList) {
    while ($r = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) {
        $notifList[] = $r;
    }
    sqlsrv_free_stmt($stmtList);
}

// Mensajes flash (para mostrar en el HTML)
$flashError   = $_SESSION['error']   ?? '';
$flashSuccess = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
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
            color: #b00020; background:#ffecec; padding:10px; border-radius:6px; margin:12px 0; text-align:center; font-weight:600;
        }
        .success-message {
            color: #036d1a; background:#e7ffef; padding:10px; border-radius:6px; margin:12px 0; text-align:center; font-weight:600;
        }
        .notification-dropdown { display:none; position:absolute; right:0; top:40px; background:#fff; border:1px solid #e5e5e5; border-radius:10px; width:320px; box-shadow:0 10px 25px rgba(0,0,0,.08); z-index:20; }
        .notification-container { position:relative; }
        .notif-item:hover { background:#fafafa; }
        .btn { cursor:pointer; }
    </style>
</head>
<body>
<header>
  <div class="brand">
    <img src="img/cmapa.png" class="logo" />
    <h1>SIA - CMAPA</h1>
  </div>
  <div class="header-right">
    <div class="notification-container">
      <button class="icon-btn" id="notif-toggle" type="button" aria-label="Notificaciones">
        <img src="<?= $unreadCount > 0 ? 'img/belldot.png' : 'img/bell.png' ?>" class="imgh3" alt="Notificaciones" />
      </button>
      <div class="notification-dropdown" id="notif-dropdown">
        <?php if ($unreadCount === 0): ?>
          <div class="notif-empty" style="padding:10px;">No hay notificaciones nuevas.</div>
        <?php else: ?>
          <ul class="notif-list" style="list-style:none; margin:0; padding:0; max-height:260px; overflow:auto;">
            <?php foreach ($notifList as $n): ?>
              <li class="notif-item"
                  style="padding:8px 10px; cursor:pointer; border-bottom:1px solid #eaeaea;"
                  onclick="window.location.href='<?= $notifTarget ?>'">
                <div class="notif-desc" style="font-size:0.95rem;">
                  <?= htmlspecialchars($n['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="notif-date" style="font-size:0.8rem; opacity:0.7;">
                  <?php
                    $f = $n['fecha'];
                    if ($f instanceof DateTime) echo $f->format('Y-m-d H:i');
                    else { $dt = @date_create(is_string($f) ? $f : 'now'); echo $dt ? $dt->format('Y-m-d H:i') : ''; }
                  ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
          <div style="padding:8px 10px;">
            <button type="button" class="btn" onclick="window.location.href='<?= $notifTarget ?>'">Ver todas</button>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <p><?= htmlspecialchars($_SESSION['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
    <div class="user-menu-container">
      <button class="icon-btn" id="user-toggle" type="button">
        <img src="img/userB.png" class="imgh2" alt="Usuario" />
      </button>
      <div class="user-dropdown" id="user-dropdown" style="display:none;">
        <p><strong>Usuario:</strong> <?= (int)($_SESSION['rol'] ?? 0) ?></p>
        <p><strong>Apodo:</strong> <?= htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        <a href="passchng.php"><button class="user-option" type="button">CAMBIAR CONTRASEÑA</button></a>
      </div>
    </div>
    <!-- botón hamburguesa -->
    <div class="menu-container">
      <button class="icon-btn" id="menu-toggle" type="button">
        <img src="img/menu.png" alt="Menú" />
      </button>
      <div class="dropdown" id="dropdown-menu" style="display:none;">
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

    <?php if (!empty($flashError)): ?>
      <div class="error-message"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (!empty($flashSuccess)): ?>
      <div class="success-message"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" class="caja-registro-form">
        <label for="idOperador">RESPONSABLE OPERATIVO</label>
        <select id="idOperador" name="idOperador" required>
            <option value="">Seleccionar responsable</option>
            <?php foreach ($operativos as $op): ?>
                <option value="<?= (int)$op['idOperador'] ?>"><?= htmlspecialchars($op['nombreCompleto'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
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
  // Toggle menú
  const toggle = document.getElementById('menu-toggle');
  const dropdown = document.getElementById('dropdown-menu');
  if (toggle && dropdown) {
    toggle.addEventListener('click', () => {
      dropdown.style.display = dropdown.style.display === 'flex' ? 'none' : 'flex';
    });
    window.addEventListener('click', (e) => {
      if (!toggle.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
    });
  }

  // Toggle usuario
  const userToggle = document.getElementById('user-toggle');
  const userDropdown = document.getElementById('user-dropdown');
  if (userToggle && userDropdown) {
    userToggle.addEventListener('click', () => {
      userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
    });
    window.addEventListener('click', (e) => {
      if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) userDropdown.style.display = 'none';
    });
  }

  // Toggle notificaciones
  const notifToggle = document.getElementById('notif-toggle');
  const notifDropdown = document.getElementById('notif-dropdown');
  if (notifToggle && notifDropdown) {
    notifToggle.addEventListener('click', () => {
      notifDropdown.style.display = notifDropdown.style.display === 'block' ? 'none' : 'block';
    });
    window.addEventListener('click', (e) => {
      if (!notifToggle.contains(e.target) && !notifDropdown.contains(e.target)) notifDropdown.style.display = 'none';
    });
  }
</script>

<?php sqlsrv_close($conn); ?>
</body>
</html>
