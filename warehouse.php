<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Verificar el rol del usuario
$rolActual = (int)($_SESSION['rol'] ?? 0);
if (!in_array($rolActual, [1, 2])) {
    header("Location: acceso_denegado.php");
    exit();
}

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
// SISTEMA DE NOTIFICACIONES DE INVENTARIO
// ========================
$alertasInventario = [];
$totalAlertas = 0;

// Para Admin (1) y Almacenista (2): Alertas de inventario
if ($conn && in_array($rolActual, [1, 2], true)) {
    // Productos en punto de reorden o sin stock
    $sqlAlertas = "SELECT 
                    p.idCodigo,
                    p.codigo,
                    p.descripcion,
                    i.cantidadActual,
                    p.puntoReorden,
                    CASE 
                        WHEN i.cantidadActual = 0 THEN 'SIN STOCK'
                        WHEN i.cantidadActual <= p.puntoReorden THEN 'PUNTO REORDEN'
                    END AS tipoAlerta,
                    CASE 
                        WHEN i.cantidadActual = 0 THEN 1
                        WHEN i.cantidadActual <= p.puntoReorden THEN 2
                    END AS prioridad
                FROM Productos p
                INNER JOIN Inventario i ON p.idCodigo = i.idCodigo
                WHERE i.cantidadActual <= p.puntoReorden
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
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <meta charset="UTF-8" />
    <title>SIA My Warehouse</title>
    <link rel="stylesheet" href="css/styleWRHS.css">
</head>

<body>
<header>
  <div class="brand">
    <img src="img/cmapa.png" class="logo" />
    <h1>SIA - CMAPA</h1>
    <a href="homepage.php" class="home-button">INICIO</a>
  </div>

  <div class="header-right">
    <!-- Campana de notificaciones de inventario -->
    <div class="notification-container">
      <button class="icon-btn" id="notif-toggle" type="button" aria-label="Alertas de Inventario">
        <img src="<?= $totalAlertas > 0 ? 'img/belldot.png' : 'img/bell.png' ?>" class="imgh3" alt="Alertas" />
        <?php if ($totalAlertas > 0): ?>
          <span class="contador-badge" id="contador-alertas"><?= $totalAlertas ?></span>
        <?php endif; ?>
      </button>    

      <div class="notification-dropdown" id="notif-dropdown" style="display:none; width: 350px; max-height: 400px; overflow-y: auto;">
        <?php if (!in_array($rolActual, [1, 2], true)): ?>
          <div class="notif-empty" style="padding:15px; text-align: center;">
            No hay notificaciones disponibles para este perfil
          </div>
        <?php elseif ($totalAlertas === 0): ?>
          <div class="notif-empty" style="padding:15px; text-align: center; color: #28a745;">
            <strong>✅ Inventario Óptimo</strong><br>
            <small>Todos los productos están en niveles adecuados</small>
          </div>
        <?php else: ?>
          <div style="padding: 10px; background-color: #262a2e; border-bottom: 1px solid #dee2e6;">
            <strong>⚠️ Alertas de Inventario (<?= $totalAlertas ?>)</strong>
            <button onclick="marcarTodasLeidas()" style="float: right; font-size: 11px; padding: 3px 8px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;">
              Marcar todas como leídas
            </button>
          </div>
          <div id="alertas-container">
            <?php foreach ($alertasInventario as $alerta): 
              $claseAlerta = ($alerta['tipoAlerta'] === 'SIN STOCK') ? 'alerta-sin-stock' : 'alerta-reorden';
              $iconoAlerta = ($alerta['tipoAlerta'] === 'SIN STOCK') ? '🔴' : '🟡';
            ?>
              <div class="alerta-item <?= $claseAlerta ?>" id="alerta-<?= $alerta['idCodigo'] ?>">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                  <div style="flex: 1;">
                    <div style="font-weight: bold; margin-bottom: 5px;">
                      <?= $iconoAlerta ?> <?= htmlspecialchars($alerta['codigo']) ?> - <?= htmlspecialchars($alerta['tipoAlerta']) ?>
                    </div>
                    <div style="font-size: 13px; color: #666;">
                      <?= htmlspecialchars($alerta['descripcion']) ?>
                    </div>
                    <div style="font-size: 12px; color: #999; margin-top: 3px;">
                      Cantidad actual: <strong><?= $alerta['cantidadActual'] ?></strong> | 
                      Punto de reorden: <strong><?= $alerta['puntoReorden'] ?></strong>
                    </div>
                  </div>
                  <button class="marca-leido-btn" onclick="marcarComoLeido(<?= $alerta['idCodigo'] ?>)">
                    ✓ Leído
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
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
        <p><strong>Tipo de usuario:</strong> <?= (int)($_SESSION['rol'] ?? 0) ?></p>
        <p><strong>Apodo:</strong> <?= htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        <a href="passchng.php"><button class="user-option" type="button">CAMBIAR CONTRASEÑA</button></a>
      </div>
    </div>

    <div class="menu-container">
      <button class="icon-btn" id="menu-toggle" type="button">
        <img src="img/menu.png" alt="Menú" />
      </button>
      <div class="dropdown" id="dropdown-menu" style="display:none;">
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

<main class="almacen-container">
  <div class="almacen-header">
    <img src="img/warehouse.png" alt="Almacén" />
    <h2>Mi Almacén</h2>
  </div>

  <div class="almacen-cards">
    <div class="card">
      <img src="img/boxes.png" class="imgs" alt="Entradas" />
      <a href="exstentry.php"><button class="card-btn">Entradas</button></a>
    </div>
    <div class="card">
      <img src="img/truck.png" class="imgs" alt="Salidas" />
      <a href="exitord.php"><button class="card-btn">Salidas</button></a>
    </div>
    <div class="card">
      <img src="img/tool-box.png" class="imgs" alt="Cajas" />
      <a href="boxes.php"><button class="card-btn">Cajas</button></a>
    </div>
  </div>
  <div class="almacen-bottom">
    <a href="devotool.php"><button class="flat-btn">Devolución de Herramientas</button></a>
  </div>
</main>

<script>
// Menú hamburguesa
const toggle = document.getElementById('menu-toggle');
const dropdown = document.getElementById('dropdown-menu');
toggle.addEventListener('click', () => {
  dropdown.style.display = dropdown.style.display === 'flex' ? 'none' : 'flex';
});
window.addEventListener('click', (e) => {
  if (!toggle.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
});

// Menú usuario
const userToggle = document.getElementById('user-toggle');
const userDropdown = document.getElementById('user-dropdown');
userToggle.addEventListener('click', () => {
  userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
});
window.addEventListener('click', (e) => {
  if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) userDropdown.style.display = 'none';
});

  // Notificaciones: toggle dropdown
  const notifToggle = document.getElementById('notif-toggle');
  const notifDropdown = document.getElementById('notif-dropdown');
  if (notifToggle && notifDropdown) {
    notifToggle.addEventListener('click', () => {
      notifDropdown.style.display = (notifDropdown.style.display === 'block') ? 'none' : 'block';
    });
    window.addEventListener('click', (e) => {
      if (!notifToggle.contains(e.target) && !notifDropdown.contains(e.target)) {
        notifDropdown.style.display = 'none';
      }
    });
  }

  // Confirmar lectura (roles 2 y 3)
  function ackUserNotif(idNotificacion) {
    fetch('php/ack_user_notif.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
      body: 'id=' + encodeURIComponent(idNotificacion)
    }).then(r => r.json()).catch(() => ({}))
      .finally(() => { window.location.reload(); });
  }

function marcarComoLeido(idCodigo) {
    // Ocultar la alerta visualmente
    const alertaElement = document.getElementById('alerta-' + idCodigo);
    if (alertaElement) {
      alertaElement.style.transition = 'opacity 0.3s';
      alertaElement.style.opacity = '0.3';
      alertaElement.style.pointerEvents = 'none';
      
      // Guardar en localStorage que fue leída
      let alertasLeidas = JSON.parse(localStorage.getItem('alertasLeidas') || '[]');
      if (!alertasLeidas.includes(idCodigo)) {
        alertasLeidas.push(idCodigo);
        localStorage.setItem('alertasLeidas', JSON.stringify(alertasLeidas));
      }
      
      // Actualizar contador
      actualizarContadorAlertas();
    }
  }

// Función para actualizar el contador de alertas
function actualizarContadorAlertas() {
  const alertasVisibles = document.querySelectorAll('.alerta-item:not([style*="opacity"])').length;
  const badge = document.querySelector('.notification-container span');
  if (badge) {
    if (alertasVisibles > 0) {
      badge.textContent = alertasVisibles;
    } else {
      badge.style.display = 'none';
    }
  }
}
  
// Al cargar la página, ocultar las alertas ya leídas
document.addEventListener('DOMContentLoaded', function() {
  const alertasLeidas = JSON.parse(localStorage.getItem('alertasLeidas') || '[]');
  alertasLeidas.forEach(idCodigo => {
    const alertaElement = document.getElementById('alerta-' + idCodigo);
    if (alertaElement) {
      alertaElement.style.display = 'none';
    }
  });
  actualizarContadorAlertas();
});
</script>

</body>
</html>
