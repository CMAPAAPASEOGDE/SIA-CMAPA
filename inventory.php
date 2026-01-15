<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Conectar a la base de datos
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

$rolActual = (int)($_SESSION['rol'] ?? 0);

// ============================
// NUEVO SISTEMA DE NOTIFICACIONES DE INVENTARIO
// ============================
$alertasInventario = [];
$totalAlertas = 0;

// Para Admin (1) y Almacenista (2): Alertas de inventario
if (in_array($rolActual, [1, 2], true)) {
    // Productos con problemas de inventario
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

// ============================
// CONSULTA INVENTARIO
// ============================
$sql = "SELECT 
            p.codigo, 
            p.descripcion, 
            p.linea, 
            p.sublinea, 
            i.cantidadActual AS cantidad, 
            p.unidad, 
            p.precio, 
            p.puntoReorden, 
            p.stockMaximo, 
            p.tipo,
            CASE 
                WHEN i.cantidadActual = 0 THEN 'Fuera de stock'
                WHEN i.cantidadActual <= p.puntoReorden THEN 'Bajo stock'
                WHEN i.cantidadActual >= p.stockMaximo THEN 'Sobre stock'
                ELSE 'En stock'
            END AS estado
        FROM Productos p
        INNER JOIN Inventario i ON p.idCodigo = i.idCodigo
        ORDER BY p.codigo ASC";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die("Error en la consulta: " . print_r(sqlsrv_errors(), true));
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Inventory</title>
    <link rel="stylesheet" href="css/StyleINV.css">
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
                      <span>Stock: <strong><?= $alerta['cantidadActual'] ?></strong></span>
                      <span>Reorden: <strong><?= $alerta['puntoReorden'] ?></strong></span>
                      <span>Máximo: <strong><?= $alerta['stockMaximo'] ?></strong></span>
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

    <p><?= htmlspecialchars($_SESSION['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

    <div class="user-menu-container">
      <button class="icon-btn" id="user-toggle" type="button">
        <img src="img/userB.png" class="imgh2" alt="Usuario" />
      </button>
      <div class="user-dropdown" id="user-dropdown">
        <p><strong>Tipo de usuario:</strong> <?= $rolActual ?></p>
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

<main class="inventory-container">
    <h2 class="inventory-title">INVENTARIO</h2>

    <!-- Filtros y búsqueda -->
    <div class="inventory-filters">
        <div class="filter-group">
            <label for="search">Buscar:</label>
            <div class="search-box">
                <input type="text" id="search" placeholder="Código, descripción...">
                <button onclick="filterTable()">Buscar</button>
            </div>
        </div>
        
        <div class="filter-group">
            <label for="linea">Línea:</label>
            <select id="linea" onchange="filterTable()">
                <option value="">Todas</option>
                <?php
                // Obtener líneas únicas
                $sqlLineas = "SELECT DISTINCT linea FROM Productos";
                $stmtLineas = sqlsrv_query($conn, $sqlLineas);
                if ($stmtLineas) {
                    while ($rowL = sqlsrv_fetch_array($stmtLineas, SQLSRV_FETCH_ASSOC)) {
                        echo '<option value="' . htmlspecialchars($rowL['linea']) . '">' . htmlspecialchars($rowL['linea']) . '</option>';
                    }
                    sqlsrv_free_stmt($stmtLineas);
                }
                ?>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="tipo">Tipo:</label>
            <select id="tipo" onchange="filterTable()">
                <option value="">Todos</option>
                <option value="Materiales">Material</option>
                <option value="Herramienta">Herramienta</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="estado">Estado:</label>
            <select id="estado" onchange="filterTable()">
                <option value="">Todos</option>
                <option value="En stock">En stock</option>
                <option value="Bajo stock">Bajo stock</option>
                <option value="Fuera de stock">Fuera de stock</option>
                <option value="Sobre stock">Sobre stock</option>
            </select>
        </div>
    </div>

    <div class="inventory-table-wrapper">
        <table class="inventory-table" id="inventory-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Línea</th>
                    <th>Sublínea</th>
                    <th>Cantidad</th>
                    <th>Unidad</th>
                    <th>Precio</th>
                    <th>Punto de Reorden</th>
                    <th>Stock maximo</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($stmt) {
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        $cantidad = $row['cantidad'];
                        $puntoReorden = $row['puntoReorden'];
                        $stockMaximo = $row['stockMaximo'];

                        // Determinar clase CSS para el estado
                        $estadoClass = '';
                        if ($row['estado'] == 'Fuera de stock') {
                            $estadoClass = 'red';
                        } elseif ($row['estado'] == 'Bajo stock') {
                            $estadoClass = 'yellow';
                        } elseif ($row['estado'] == 'Sobre stock') {
                            $estadoClass = 'green-bright';
                        } else {
                            $estadoClass = 'green';
                        }
                        
                        // Clase para cantidad baja/alta
                        $cantidadClass = ($cantidad <= $puntoReorden) ? 'low-stock' : (($cantidad >= $stockMaximo) ? 'high-stock' : '');
                        
                        // Ocultar precio si el usuario es Almacenista (idRol = 2)
                        $precio = ($idRol === 2) ? '<span class="censored">******</span>' : '$' . number_format($row['precio'], 2);
                        
                        echo "<tr>
                                <td>".htmlspecialchars($row['codigo'])."</td>
                                <td>".htmlspecialchars($row['descripcion'])."</td>
                                <td>".htmlspecialchars($row['linea'])."</td>
                                <td>".htmlspecialchars($row['sublinea'])."</td>
                                <td class='{$cantidadClass}'>".(int)$cantidad."</td>
                                <td>".htmlspecialchars($row['unidad'])."</td>
                                <td>{$precio}</td>
                                <td>".(int)$puntoReorden."</td>
                                <td>".(int)$stockMaximo."</td>
                                <td>".htmlspecialchars($row['tipo'])."</td>
                                <td><span class='estado {$estadoClass}'>".htmlspecialchars($row['estado'])."</span></td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='11'>No se encontraron registros</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</main>

<script src="js/notificaciones.js"></script>
<script src="js/menus.js"></script>
<script src="js/inventory-filters.js"></script>

<?php
// Cerrar conexión a la base de datos
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
</body>
</html>
