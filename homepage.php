<?php
// Iniciar sesión
session_start();

// Verificar sesión
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$rolActual   = (int)($_SESSION['rol'] ?? 0);
$unreadCount = 0;
$notifList   = [];

if (in_array($rolActual, [1,2,3], true)) {
    $serverName = "sqlserver-sia.database.windows.net";
    $connectionOptions = [
        "Database" => "db_sia",
        "Uid"      => "cmapADMIN",
        "PWD"      => "@siaADMN56*",
        "Encrypt"  => true,
        "TrustServerCertificate" => false
    ];
    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if ($conn) {
        if ($rolActual === 1) {
            // ADMIN: notificaciones desde Modificaciones (pendientes)
            $stmtCount = sqlsrv_query(
                $conn,
                "SELECT COUNT(*) AS c
                   FROM Modificaciones
                  WHERE solicitudRevisada = 0"
            );
            $stmtList = sqlsrv_query(
                $conn,
                "SELECT TOP 10
                        M.idModificacion,
                        M.descripcion,
                        M.fecha,
                        M.tipo,
                        M.cantidad,
                        P.codigo AS codigoProducto
                   FROM Modificaciones M
              LEFT JOIN Productos P ON P.idCodigo = M.idCodigo
                  WHERE M.solicitudRevisada = 0
               ORDER BY M.fecha DESC"
            );
        } else {
            // USUARIOS (2 y 3): notificaciones desde Notificaciones (no leídas)
            $stmtCount = sqlsrv_query(
                $conn,
                "SELECT COUNT(*) AS c
                   FROM Notificaciones
                  WHERE estatusRevision = 0"
            );
            $stmtList = sqlsrv_query(
                $conn,
                "SELECT TOP 10
                        N.idNotificacion,
                        N.descripcion AS comentarioAdmin,
                        N.fechaNotificacion,
                        P.codigo AS codigoProducto
                   FROM Notificaciones N
              LEFT JOIN Modificaciones M ON M.idModificacion = N.idModificacion
              LEFT JOIN Productos      P ON P.idCodigo       = M.idCodigo
                  WHERE N.estatusRevision = 0
               ORDER BY N.fechaNotificacion DESC"
            );
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

        sqlsrv_close($conn);
    }
}
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <title>SIA Homepage</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styleHP.css">
</head>
<body>

<header>
  <div class="brand">
    <img src="img/cmapa.png" class="logo" />
    <h1>SIA - CMAPA</h1>
    <!-- NUEVO BOTÓN DE INICIO -->
    <a href="homepage.php" class="home-button">INICIO</a>
  </div>

  <div class="header-right">
    <!-- Campana de notificaciones de inventario -->
    <div class="notification-container">
      <button class="icon-btn" id="notif-toggle" type="button" aria-label="Alertas de Inventario">
        <img src="<?= $totalAlertas > 0 ? 'img/belldot.png' : 'img/bell.png' ?>" class="imgh3" alt="Alertas" />
        <?php if ($totalAlertas > 0): ?>
            <span style="position: absolute; top: -5px; right: -5px; background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px;"><?= $totalAlertas ?></span>
        <?php endif; ?>
      </button>

      <div class="notification-dropdown" id="notif-dropdown" style="display:none; width: 350px; max-height: 400px; overflow-y: auto;">
        <?php if ($totalAlertas === 0): ?>
          <div class="notif-empty" style="padding:15px; text-align: center;">
            ✅ Todo el inventario está en niveles óptimos
          </div>
        <?php else: ?>
          <div style="padding: 10px; background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;">
            <strong>⚠️ Alertas de Inventario (<?= $totalAlertas ?>)</strong>
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
        <p><strong>Usuario:</strong> <?= (int)($_SESSION['rol'] ?? 0) ?></p>
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
        <a href="admin.php">Menu de administrador</a>
        <a href="about.php">Acerca de</a>
        <a href="help.php">Ayuda</a>
        <a href="logout.php">Cerrar Sesión</a>
      </div>
    </div>
  </div>
</header>


<main class="menu">
  <div class="card">
    <img src="img/warehouse.png" alt="Almacén" />
    <a href="warehouse.php"><button class="card-btn">MI ALMACEN</button></a>
  </div>
  <div class="card">
    <img src="img/inventory-management.png" alt="Inventario" />
    <a href="inventory.php"><button class="card-btn">INVENTARIO</button></a>
  </div>
  <div class="card">
    <img src="img/documentation.png" alt="Reportes" />
    <a href="reports.php"><button class="card-btn">REPORTES</button></a>
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

// Notificaciones
const notifToggle   = document.getElementById('notif-toggle');
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

// Confirmar lectura (roles 2 y 3): marca estatusRevision=1 y redirige a inventario
function ackUserNotif(idNotificacion) {
  fetch('php/ack_user_notif.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
    body: 'id=' + encodeURIComponent(idNotificacion)
  })
  .then(r => r.json()).catch(() => ({}))
  .finally(() => { window.location.href = 'inventory.php'; });
}
</script>

<script>
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
