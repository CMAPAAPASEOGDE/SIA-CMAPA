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

$rolActual = (int)($_SESSION['rol'] ?? 0);

// Conexión a la base de datos
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
    "Database" => "db_sia",
    "Uid"      => "cmapADMIN",
    "PWD"      => "@siaADMN56*",
    "Encrypt"  => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);

// ========================
// NUEVO SISTEMA DE NOTIFICACIONES DE INVENTARIO
// ========================
$alertasInventario = [];
$totalAlertas = 0;

// Solo para Admin (1) y Almacenista (2)
if ($conn && in_array($rolActual, [1, 2], true)) {
    // Consulta para detectar productos con problemas de inventario
    $sqlAlertas = "SELECT 
                    p.idCodigo,
                    p.codigo,
                    p.descripcion,
                    i.cantidadActual,
                    p.puntoReorden,
                    p.stockMaximo,
                    CASE 
                        WHEN i.cantidadActual = 0 THEN 'SIN STOCK'
                        WHEN i.cantidadActual <= p.puntoReorden THEN 'BAJO STOCK'
                        WHEN i.cantidadActual >= p.stockMaximo THEN 'SOBRE STOCK'
                    END AS tipoAlerta,
                    CASE 
                        WHEN i.cantidadActual = 0 THEN 1
                        WHEN i.cantidadActual <= p.puntoReorden THEN 2
                        WHEN i.cantidadActual >= p.stockMaximo THEN 3
                    END AS prioridad
                FROM Productos p
                INNER JOIN Inventario i ON p.idCodigo = i.idCodigo
                WHERE i.cantidadActual = 0 
                   OR i.cantidadActual <= p.puntoReorden 
                   OR i.cantidadActual >= p.stockMaximo
                ORDER BY prioridad ASC, i.cantidadActual ASC";
    
    $stmtAlertas = sqlsrv_query($conn, $sqlAlertas);
    if ($stmtAlertas) {
        while ($alerta = sqlsrv_fetch_array($stmtAlertas, SQLSRV_FETCH_ASSOC)) {
            $alertasInventario[] = $alerta;
        }
        sqlsrv_free_stmt($stmtAlertas);
    }
    
    $totalAlertas = count($alertasInventario);
}

if ($conn) {
    sqlsrv_close($conn);
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
    <a href="homepage.php" class="home-button">INICIO</a>
  </div>

  <div class="header-right">
    <!-- Sistema de Notificaciones de Inventario -->
    <?php if (in_array($rolActual, [1, 2], true)): ?>
    <div class="notification-container">
      <button class="icon-btn" id="notif-toggle" type="button" aria-label="Alertas de Inventario">
        <img src="<?= $totalAlertas > 0 ? 'img/belldot.png' : 'img/bell.png' ?>" 
        class="notif-icon" alt="Alertas" />
        <?php if ($totalAlertas > 0): ?>
          <span class="contador-badge"><?= $totalAlertas ?></span>
        <?php endif; ?>
      </button>

      <div class="notification-dropdown" id="notif-dropdown">
        <?php if ($totalAlertas === 0): ?>
          <div class="notif-empty">
            <div class="check-icon">✅</div>
            <strong>Inventario Óptimo</strong>
            <p>Todos los productos están en niveles adecuados</p>
          </div>
        <?php else: ?>
          <div class="notif-header">
            <span class="notif-title">⚠️ Alertas de Inventario (<?= $totalAlertas ?>)</span>
            <button class="btn-marcar-todas" onclick="marcarTodasLeidas()">
              Marcar todas como leídas
            </button>
          </div>
          <div class="alertas-container">
            <?php foreach ($alertasInventario as $alerta): 
              $claseAlerta = '';
              $iconoAlerta = '';
              
              switch($alerta['tipoAlerta']) {
                case 'SIN STOCK':
                  $claseAlerta = 'alerta-sin-stock';
                  $iconoAlerta = '🔴';
                  break;
                case 'BAJO STOCK':
                  $claseAlerta = 'alerta-bajo-stock';
                  $iconoAlerta = '🟡';
                  break;
                case 'SOBRE STOCK':
                  $claseAlerta = 'alerta-sobre-stock';
                  $iconoAlerta = '🟢';
                  break;
              }
            ?>
              <div class="alerta-item <?= $claseAlerta ?>" data-id="<?= $alerta['idCodigo'] ?>">
                <div class="alerta-content">
                  <div class="alerta-info">
                    <div class="alerta-header">
                      <span class="alerta-icono"><?= $iconoAlerta ?></span>
                      <strong><?= htmlspecialchars($alerta['codigo']) ?></strong>
                      <span class="alerta-tipo"><?= htmlspecialchars($alerta['tipoAlerta']) ?></span>
                    </div>
                    <div class="alerta-descripcion">
                      <?= htmlspecialchars($alerta['descripcion']) ?>
                    </div>
                    <div class="alerta-detalles">
                      <span>Stock actual: <strong><?= $alerta['cantidadActual'] ?></strong></span>
                      <span>Punto reorden: <strong><?= $alerta['puntoReorden'] ?></strong></span>
                      <span>Stock máximo: <strong><?= $alerta['stockMaximo'] ?></strong></span>
                    </div>
                  </div>
                  <button class="btn-marcar-leido" onclick="marcarComoLeido(<?= $alerta['idCodigo'] ?>)">
                    ✓
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <p><?= htmlspecialchars($_SESSION['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

    <div class="user-menu-container">
      <button class="icon-btn" id="user-toggle" type="button">
        <img src="img/userB.png" class="imgh2" alt="Usuario" />
      </button>
      <div class="user-dropdown" id="user-dropdown">
        <p><strong>Tipo de Usuario:</strong> <?= $rolActual ?></p>
        <p><strong>Apodo:</strong> <?= htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        <a href="passchng.php"><button class="user-option" type="button">CAMBIAR CONTRASEÑA</button></a>
      </div>
    </div>

    <div class="menu-container">
      <button class="icon-btn" id="menu-toggle" type="button">
        <img src="img/menu.png" alt="Menú" />
      </button>
      <div class="dropdown" id="dropdown-menu">
        <a href="homepage.php">Inicio</a>
        <a href="mnthclsr.php">Cierre de mes</a>
        <?php if ($rolActual === 1): ?>
          <a href="admin.php">Menu de administrador</a>
        <?php endif; ?>
        <a href="about.php">Acerca de</a>
        <a href="help.php">Ayuda</a>
        <a href="logout.php">Cerrar Sesión</a>
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

<script src="js/notificaciones.js"></script>
<script src="js/menus.js"></script>

</body>
</html>
