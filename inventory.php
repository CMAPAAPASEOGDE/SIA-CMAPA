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

// ============================
// NOTIFICACIONES (para header)
// ============================
$rolActual   = (int)($_SESSION['rol'] ?? 0);
$notifTarget = ($rolActual === 1) ? 'admnrqst.php' : 'mis_notifs.php'; // úsalo en el header
$unreadCount = 0;
$notifList   = [];

// Admin ve SOLO notifs destinadas a admin (idRol = 1)
// Usuario ve SOLO notifs destinadas a su rol (idRol = rolActual)
if ($rolActual === 1) {
    $sqlCount = "SELECT COUNT(*) AS c FROM Notificaciones WHERE solicitudRevisada = 0 AND idRol = 1";
    $stmtCount = sqlsrv_query($conn, $sqlCount);

    $sqlList = "SELECT TOP 10 idNotificacion, descripcion, fecha
                FROM Notificaciones
                WHERE solicitudRevisada = 0 AND idRol = 1
                ORDER BY fecha DESC";
    $stmtList = sqlsrv_query($conn, $sqlList);
} else {
    $sqlCount = "SELECT COUNT(*) AS c FROM Notificaciones WHERE solicitudRevisada = 0 AND idRol = ?";
    $stmtCount = sqlsrv_query($conn, $sqlCount, [$rolActual]);

    $sqlList = "SELECT TOP 10 idNotificacion, descripcion, fecha
                FROM Notificaciones
                WHERE solicitudRevisada = 0 AND idRol = ?
                ORDER BY fecha DESC";
    $stmtList = sqlsrv_query($conn, $sqlList, [$rolActual]);
}

if ($stmtCount !== false) {
    $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
    $unreadCount = (int)($row['c'] ?? 0);
    sqlsrv_free_stmt($stmtCount);
}
if ($stmtList !== false) {
    while ($r = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) {
        $notifList[] = $r; // cada $r tiene: idNotificacion, descripcion, fecha
    }
    sqlsrv_free_stmt($stmtList);
}

// ============================
// CONSULTA INVENTARIO (igual)
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
        INNER JOIN Inventario i ON p.idCodigo = i.idCodigo";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die("Error en la consulta: " . print_r(sqlsrv_errors(), true));
}

// (si luego NO usarás más la conexión, puedes cerrarla aquí)
// sqlsrv_close($conn);

// Rol del usuario por si lo necesitas abajo
$idRol = (int)$_SESSION['rol'];
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
  </div>

  <div class="header-right">
    <div class="notification-container">
      <button class="icon-btn" id="notif-toggle" type="button" aria-label="Notificaciones">
        <img
          src="<?= $unreadCount > 0 ? 'img/belldot.png' : 'img/bell.png' ?>"
          class="imgh3"
          alt="Notificaciones"
        />
      </button>

      <div class="notification-dropdown" id="notif-dropdown" style="display:none;">
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
                    while ($row = sqlsrv_fetch_array($stmtLineas, SQLSRV_FETCH_ASSOC)) {
                        echo '<option value="' . htmlspecialchars($row['linea']) . '">' . htmlspecialchars($row['linea']) . '</option>';
                    }
                }
                ?>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="tipo">Tipo:</label>
            <select id="tipo" onchange="filterTable()">
                <option value="">Todos</option>
                <option value="Material">Material</option>
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
        <table class="inventory-table">
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
                        
                        // Clase para cantidad baja
                        $cantidadClass = ($cantidad <= $puntoReorden) ? 'low-stock' : (($cantidad >= $stockMaximo) ? 'high-stock' : '');
                        
                        // Ocultar precio si el usuario es Almacenista (idRol = 2)
                        $precio = ($idRol === 2) ? '<span class="censored">******</span>' : '$' . number_format($row['precio'], 2);
                        
                        echo "<tr>
                                <td>{$row['codigo']}</td>
                                <td>{$row['descripcion']}</td>
                                <td>{$row['linea']}</td>
                                <td>{$row['sublinea']}</td>
                                <td class='{$cantidadClass}'>{$cantidad}</td>
                                <td>{$row['unidad']}</td>
                                <td>{$precio}</td>
                                <td>{$puntoReorden}</td>
                                <td>{$stockMaximo}</td>
                                <td>{$row['tipo']}</td>
                                <td><span class='estado {$estadoClass}'>{$row['estado']}</span></td>
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

<script>
  // Filtrado de la tabla
  function filterTable() {
    const searchText = document.getElementById('search').value.toLowerCase();
    const linea = document.getElementById('linea').value;
    const tipo = document.getElementById('tipo').value;
    const estado = document.getElementById('estado').value;
    
    const table = document.getElementById('inventory-table');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
      const cells = rows[i].getElementsByTagName('td');
      const show = 
        (searchText === '' || 
          cells[0].textContent.toLowerCase().includes(searchText) || 
          cells[1].textContent.toLowerCase().includes(searchText)) &&
        (linea === '' || cells[2].textContent === linea) &&
        (tipo === '' || cells[9].textContent === tipo) &&
        (estado === '' || cells[10].textContent.includes(estado));
        
      rows[i].style.display = show ? '' : 'none';
    }
  }
  
  // Cambiar página (simulado)
  function changePage(page) {
    const buttons = document.querySelectorAll('.pagination button');
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Simular carga de datos de la página seleccionada
    console.log('Cargando página', page);
  }
  
  // Inicializar la tabla
  document.addEventListener('DOMContentLoaded', filterTable);
</script>

<?php
// Cerrar conexión a la base de datos
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
</body>
</html>
