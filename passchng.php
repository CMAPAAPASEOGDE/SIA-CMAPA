<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// ------------------ Cambio de contraseña ------------------
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $oldPass     = $_POST['old_Pass'] ?? '';
    $newPass     = $_POST['new_pass'] ?? '';
    $confirmPass = $_POST['confirm-pass'] ?? '';

    if (empty($oldPass) || empty($newPass) || empty($confirmPass)) {
        $error = "Todos los campos son obligatorios";
    } elseif ($newPass !== $confirmPass) {
        $error = "Las nuevas contraseñas no coinciden";
    } elseif ($oldPass === $newPass) {
        $error = "La nueva contraseña debe ser diferente a la actual";
    } elseif (strlen($newPass) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres";
    } else {
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
            $error = "Error de conexión: " . print_r(sqlsrv_errors(), true);
        } else {
            $user_id = (int)$_SESSION['user_id'];

            $sql = "SELECT contrasena FROM usuarios WHERE idUsuario = ?";
            $params = [$user_id];
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt === false) {
                $error = "Error en la consulta: " . print_r(sqlsrv_errors(), true);
            } else {
                if (sqlsrv_has_rows($stmt)) {
                    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                    // Nota: en producción usar hashing (password_hash / password_verify)
                    if ($oldPass === $row['contrasena']) {
                        $updateSql    = "UPDATE usuarios SET contrasena = ? WHERE idUsuario = ?";
                        $updateParams = [$newPass, $user_id];
                        $updateStmt   = sqlsrv_query($conn, $updateSql, $updateParams);

                        if ($updateStmt === false) {
                            $error = "Error al actualizar la contraseña: " . print_r(sqlsrv_errors(), true);
                        } else {
                            $success = "¡Contraseña actualizada correctamente!";
                        }
                    } else {
                        $error = "La contraseña actual es incorrecta";
                    }
                } else {
                    $error = "Usuario no encontrado";
                }
            }

            if (isset($stmt) && $stmt !== false) sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);
        }
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
    <title>SIA Password Change</title>
    <link rel="stylesheet" href="css/StylePSCH.css">
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

<main class="pwd-container">
  <form id="form-pass" method="POST" action="passchng.php">
    <div class="pwd-box">
      <?php if (!empty($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="success-message"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <div class="pwd-field">
        <img src="img/padlock.png" class="pwd-icon" alt="Lock">
        <input type="password" id="old-pass" name="old_Pass" placeholder="Contraseña Anterior" required>
      </div>

      <div class="pwd-field">
        <img src="img/padlock.png" class="pwd-icon" alt="Lock">
        <input type="password" id="new-pass" name="new_pass" placeholder="Contraseña Nueva" required>
      </div>

      <div class="pwd-field">
        <img src="img/padlock.png" class="pwd-icon" alt="Lock">
        <input type="password" id="confirm-pass" name="confirm-pass" placeholder="Confirmar Contraseña" required>
      </div>

      <button type="submit" class="accept-btn" id="pwd-accept">ACEPTAR</button>

      <div class="password-strength">
        <div class="strength-bar" id="strength-bar"></div>
      </div>
      <div class="strength-text" id="strength-text"></div>

      <p id="mensaje-resultado" class="mensaje"></p>
    </div>
  </form>
</main>

<script src="js/notificaciones.js"></script>
<script src="js/menus.js"></script>

</body>
</html>
