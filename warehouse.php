<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Si no hay sesión activa, redirigir al login
    header("Location: index.php");
    exit();
}

// Verificar el rol del usuario
$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1, 2])) {
    header("Location: acceso_denegado.php");
    exit();
}

// -------- Notificaciones (nuevo esquema) --------
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
            // ADMIN: pendientes en Modificaciones
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
            // USUARIOS (2 y 3): avisos desde Notificaciones
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
                        N.descripcion      AS comentarioAdmin,
                        N.fechaNotificacion,
                        P.codigo           AS codigoProducto
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

// ============================
// NOTIFICACIONES INVENTARIO
// ============================
$rolActual = (int)($_SESSION['rol'] ?? 0);
$alertasInventario = [];
$totalAlertas = 0;

// Para Admin (1) y Almacenista (2): Alertas de inventario
if (in_array($rolActual, [1, 2], true)) {
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

      <div class="notification-dropdown" id="notif-dropdown" style="display:none;">
        <?php if ($unreadCount === 0): ?>
          <div class="notif-empty" style="padding:10px;">No hay notificaciones nuevas.</div>

        <?php elseif ($rolActual === 1): ?>
          <!-- ADMIN: desde Modificaciones -->
          <ul class="notif-list" style="list-style:none; margin:0; padding:0; max-height:260px; overflow:auto;">
            <?php foreach ($notifList as $n): ?>
              <?php
                $f = $n['fecha'] ?? null;
                $fechaTxt = ($f instanceof DateTime)
                              ? $f->format('Y-m-d H:i')
                              : (($dt = @date_create(is_string($f) ? $f : 'now')) ? $dt->format('Y-m-d H:i') : '');
                $tipoTxt = strtoupper((string)($n['tipo'] ?? ''));
                $qtyTxt  = isset($n['cantidad']) ? ' • Cant.: '.(int)$n['cantidad'] : '';
                $codigo  = (string)($n['codigoProducto'] ?? '');
              ?>
              <li class="notif-item"
                  style="padding:8px 10px; cursor:pointer; border-bottom:1px solid #eaeaea;"
                  onclick="window.location.href='admnrqst.php'">
                <div class="notif-desc" style="font-size:0.95rem;">
                  [<?= htmlspecialchars($tipoTxt, ENT_QUOTES, 'UTF-8') ?>]
                  <strong><?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?></strong>
                  <?= $qtyTxt ?> — <?= htmlspecialchars($n['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="notif-date" style="font-size:0.8rem; opacity:0.7;"><?= $fechaTxt ?></div>
              </li>
            <?php endforeach; ?>
          </ul>

        <?php else: ?>
          <!-- USUARIOS 2 y 3: desde Notificaciones -->
          <ul class="notif-list" style="list-style:none; margin:0; padding:0; max-height:260px; overflow:auto;">
            <?php foreach ($notifList as $n): ?>
              <?php
                $idNoti   = (int)($n['idNotificacion'] ?? 0);
                $codigo   = (string)($n['codigoProducto'] ?? '');
                $coment   = (string)($n['comentarioAdmin'] ?? '');
                $f        = $n['fechaNotificacion'] ?? null;
                $fechaTxt = ($f instanceof DateTime)
                          ? $f->format('Y-m-d H:i')
                          : (($dt = @date_create(is_string($f) ? $f : 'now')) ? $dt->format('Y-m-d H:i') : '');
              ?>
              <li class="notif-item"
                  style="padding:8px 10px; cursor:pointer; border-bottom:1px solid #eaeaea;"
                  onclick="ackUserNotif(<?= $idNoti ?>)">
                <div class="notif-desc" style="font-size:0.95rem;">
                  <strong><?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?></strong> —
                  <?= htmlspecialchars($coment, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="notif-date" style="font-size:0.8rem; opacity:0.7;"><?= $fechaTxt ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    

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
    <a href="modif.php"><button class="flat-btn">Modificación de almacén</button></a>
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
