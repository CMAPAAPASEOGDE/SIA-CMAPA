<?php
session_start();

if (isset($_SESSION['exit_error'])) {
    $errorMsg = $_SESSION['exit_error'];
    unset($_SESSION['exit_error']);
    echo '<div style="background:#fee;border:1px solid #c00;padding:10px;margin:10px;border-radius:5px;">';
    echo '<strong>Error:</strong> ' . htmlspecialchars($errorMsg);
    echo '</div>';
}

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
$queryProd = "SELECT idCodigo, codigo, descripcion FROM Productos ORDER BY codigo ASC";
$stmt = sqlsrv_query($conn, $queryProd);
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $productos[] = $row;
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
    <title>SIA Exit Without Order</title>
    <link rel="stylesheet" href="css/StyleETNOOD.css">
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
                
            </div>
        </div>

        <div class="salida-actions">
            <a href="warehouse.php"><button type="button" class="btn cancel">CANCELAR</button></a>
            <button type="button" class="btn add" onclick="agregarElemento()">AÑADIR ELEMENTOS</button>
            <button type="button" class="btn remove" onclick="eliminarUltimo()" style="background-color: #ffc107; color: #333;">ELIMINAR ÚLTIMO</button>
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
  nuevo.id = `elemento-${contador}`;
  nuevo.innerHTML = `
    <select name="elementos[${contador}][idCodigo]" class="codigo-select" onchange="cargarNombre(this)">
      <option value="">Seleccionar código</option>
      ${productos.map(p => `<option value="${p.idCodigo}" data-nombre="${p.descripcion}">${p.codigo} - ${p.descripcion}</option>`).join('')}
    </select>
    <input type="text" name="elementos[${contador}][nombre]" placeholder="NOMBRE" readonly />
    <input type="number" name="elementos[${contador}][cantidad]" placeholder="CANTIDAD" min="1" required />
    <button type="button" class="btn-eliminar" onclick="eliminarElemento('elemento-${contador}')" style="background-color: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer;">✕</button>
  `;
  container.appendChild(nuevo);
  contador++;
}

function eliminarElemento(elementId) {
  const elemento = document.getElementById(elementId);
  if (elemento) {
    // Verificar que quede al menos un elemento
    const totalElementos = document.querySelectorAll('.salida-row.item').length;
    if (totalElementos > 1) {
      elemento.remove();
    } else {
      alert('Debe mantener al menos un elemento en la orden');
    }
  }
}

function eliminarUltimo() {
  const elementos = document.querySelectorAll('.salida-row.item');
  if (elementos.length > 1) {
    elementos[elementos.length - 1].remove();
  } else {
    alert('Debe mantener al menos un elemento en la orden');
  }
}
</script>

<script src="js/notificaciones.js"></script>
<script src="js/menus.js"></script>

</body>
</html>
