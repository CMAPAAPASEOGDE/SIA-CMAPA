<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Si no hay sesión activa, redirigir al login
    header("Location: index.php");
    exit();
}

// Conectar a la base de datos
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = array(
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die("Error de conexión: " . print_r(sqlsrv_errors(), true));
}

// Consulta para obtener datos del inventario
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

// Obtener el rol del usuario
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
      <button class="icon-btn" id="notif-toggle">
        <img src="img/bell.png" class="imgh3" alt="Notificaciones" />
      </button>
      <div class="notification-dropdown" id="notif-dropdown"></div>
    </div>
    <p> <?= $_SESSION['usuario'] ?> </p>
    <div class="user-menu-container">
      <button class="icon-btn" id="user-toggle">
        <img src="img/userB.png" class="imgh2" alt="Usuario" />
      </button>
      <div class="user-dropdown" id="user-dropdown">
        <p><strong>Usuario:</strong> <?= $_SESSION[ 'rol' ]?></p>
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
