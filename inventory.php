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
// NOTIFICACIONES (nuevo header)
// ============================
$rolActual   = (int)($_SESSION['rol'] ?? 0);
$unreadCount = 0;
$notifList   = [];
$notifTarget = ($rolActual === 1) ? 'admnrqst.php' : 'inventory.php';

// Admin (rol 1): pendientes en Modificaciones
if ($rolActual === 1) {
    $stmtCount = sqlsrv_query($conn,
        "SELECT COUNT(*) AS c
           FROM Modificaciones
          WHERE solicitudRevisada = 0");
    $stmtList  = sqlsrv_query($conn,
        "SELECT TOP 10 M.idModificacion, M.descripcion, M.fechaSolicitud, M.tipo, M.cantidad,
                P.codigo AS codigoProducto
           FROM Modificaciones M
      LEFT JOIN Productos P ON P.idCodigo = M.idCodigo
          WHERE M.solicitudRevisada = 0
       ORDER BY M.fechaSolicitud DESC");
}
// Usuarios 2 y 3: avisos desde Notificaciones
elseif (in_array($rolActual, [2,3], true)) {
    $stmtCount = sqlsrv_query($conn,
        "SELECT COUNT(*) AS c
           FROM Notificaciones
          WHERE estatusRevision = 0");
    $stmtList  = sqlsrv_query($conn,
        "SELECT TOP 10 N.idNotificacion,
                N.descripcion AS comentarioAdmin,
                N.fechaNotificacion,
                P.codigo      AS codigoProducto
           FROM Notificaciones N
      LEFT JOIN Modificaciones M ON M.idModificacion = N.idModificacion
      LEFT JOIN Productos      P ON P.idCodigo       = M.idCodigo
          WHERE N.estatusRevision = 0
       ORDER BY N.fechaNotificacion DESC");
}

if (isset($stmtCount) && $stmtCount) {
    $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
    $unreadCount = (int)($row['c'] ?? 0);
    sqlsrv_free_stmt($stmtCount);
}
if (isset($stmtList) && $stmtList) {
    while ($r = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) {
        $notifList[] = $r;
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

// Rol del usuario por si lo usas abajo
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
    <!-- Campana de notificaciones (nuevo render) -->
    <div class="notification-container">
      <button class="icon-btn" id="notif-toggle" type="button" aria-label="Notificaciones">
        <img src="<?= $unreadCount > 0 ? 'img/belldot.png' : 'img/bell.png' ?>" class="imgh3" alt="Notificaciones" />
      </button>

      <div class="notification-dropdown" id="notif-dropdown" style="display:none;">
        <?php if ($unreadCount === 0): ?>
          <div class="notif-empty" style="padding:10px;">No hay notificaciones nuevas.</div>

        <?php elseif ($rolActual === 1): ?>
          <ul class="notif-list" style="list-style:none; margin:0; padding:0; max-height:260px; overflow:auto;">
            <?php foreach ($notifList as $n): 
              $f = $n['fechaSolicitud'] ?? null;
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
                  <strong><?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?></strong><?= $qtyTxt ?> —
                  <?= htmlspecialchars($n['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="notif-date" style="font-size:0.8rem; opacity:0.7;"><?= $fechaTxt ?></div>
              </li>
            <?php endforeach; ?>
          </ul>

        <?php else: ?>
          <ul class="notif-list" style="list-style:none; margin:0; padding:0; max-height:260px; overflow:auto;">
            <?php foreach ($notifList as $n):
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
                    while ($rowL = sqlsrv_fetch_array($stmtLineas, SQLSRV_FETCH_ASSOC)) {
                        echo '<option value="' . htmlspecialchars($rowL['linea']) . '">' . htmlspecialchars($rowL['linea']) . '</option>';
                    }
                    sqlsrv_free_stmt($stmtLineas);
                }
                ?>
            </select>
        </div>

        <div class="filter-group">
            <label for="sublinea">Sublínea:</label>
            <select id="sublinea" onchange="filterTable()">
                <option value="">Todas</option>
                <?php
                // Obtener sublíneas únicas
                $sqlSublineas = "SELECT DISTINCT sublinea FROM Productos WHERE sublinea IS NOT NULL AND sublinea != '' ORDER BY sublinea";
                $stmtSublineas = sqlsrv_query($conn, $sqlSublineas);
                if ($stmtSublineas) {
                    while ($rowS = sqlsrv_fetch_array($stmtSublineas, SQLSRV_FETCH_ASSOC)) {
                        echo '<option value="' . htmlspecialchars($rowS['sublinea']) . '">' . htmlspecialchars($rowS['sublinea']) . '</option>';
                    }
                    sqlsrv_free_stmt($stmtSublineas);
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

<script>
  // Menú hamburguesa
  const toggle = document.getElementById('menu-toggle');
  const dropdown = document.getElementById('dropdown-menu');
  if (toggle && dropdown) {
    toggle.addEventListener('click', () => {
      dropdown.style.display = dropdown.style.display === 'flex' ? 'none' : 'flex';
    });
    window.addEventListener('click', (e) => {
      if (!toggle.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });
  }

  // Menú de usuario
  const userToggle = document.getElementById('user-toggle');
  const userDropdown = document.getElementById('user-dropdown');
  if (userToggle && userDropdown) {
    userToggle.addEventListener('click', () => {
      userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
    });
    window.addEventListener('click', (e) => {
      if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) {
        userDropdown.style.display = 'none';
      }
    });
  }

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

    // Datos de sublíneas para filtrado dinámico
  const sublineasData = {
    <?php
    // Obtener todas las líneas con sus sublíneas
    $sqlLineasSublineas = "SELECT DISTINCT linea, sublinea FROM Productos WHERE sublinea IS NOT NULL AND sublinea != '' ORDER BY linea, sublinea";
    $stmtLineasSublineas = sqlsrv_query($conn, $sqlLineasSublineas);
    $lineasSublineas = [];
    if ($stmtLineasSublineas) {
        while ($row = sqlsrv_fetch_array($stmtLineasSublineas, SQLSRV_FETCH_ASSOC)) {
            $linea = $row['linea'];
            $sublinea = $row['sublinea'];
            if (!isset($lineasSublineas[$linea])) {
                $lineasSublineas[$linea] = [];
            }
            $lineasSublineas[$linea][] = $sublinea;
        }
        sqlsrv_free_stmt($stmtLineasSublineas);
        
        // Generar JavaScript
        foreach ($lineasSublineas as $linea => $sublineas) {
            echo "'" . addslashes($linea) . "': " . json_encode($sublineas) . ",\n";
        }
    }
    ?>
  };

  // Actualizar sublíneas según línea seleccionada
  function updateSublineas() {
    const lineaSelect = document.getElementById('linea');
    const sublineaSelect = document.getElementById('sublinea');
    const lineaSeleccionada = lineaSelect.value;
    
    // Guardar la selección actual
    const seleccionActual = sublineaSelect.value;
    
    // Limpiar opciones excepto "Todas"
    sublineaSelect.innerHTML = '<option value="">Todas</option>';
    
    if (lineaSeleccionada && sublineasData[lineaSeleccionada]) {
        // Agregar sublíneas de la línea seleccionada
        sublineasData[lineaSeleccionada].forEach(sublinea => {
            const option = document.createElement('option');
            option.value = sublinea;
            option.textContent = sublinea;
            sublineaSelect.appendChild(option);
        });
        
        // Restaurar selección anterior si existe en las nuevas opciones
        if (seleccionActual && sublineasData[lineaSeleccionada].includes(seleccionActual)) {
            sublineaSelect.value = seleccionActual;
        }
    } else if (!lineaSeleccionada) {
        // Si no hay línea seleccionada, mostrar todas las sublíneas
        <?php
        $sqlTodasSublineas = "SELECT DISTINCT sublinea FROM Productos WHERE sublinea IS NOT NULL AND sublinea != '' ORDER BY sublinea";
        $stmtTodasSublineas = sqlsrv_query($conn, $sqlTodasSublineas);
        if ($stmtTodasSublineas) {
            while ($row = sqlsrv_fetch_array($stmtTodasSublineas, SQLSRV_FETCH_ASSOC)) {
                echo "const option = document.createElement('option');";
                echo "option.value = '" . addslashes($row['sublinea']) . "';";
                echo "option.textContent = '" . addslashes($row['sublinea']) . "';";
                echo "sublineaSelect.appendChild(option);";
            }
            sqlsrv_free_stmt($stmtTodasSublineas);
        }
        ?>
        
        // Restaurar selección anterior si existe
        if (seleccionActual) {
            sublineaSelect.value = seleccionActual;
        }
    }
    
    // Volver a filtrar la tabla
    filterTable();
  }

  // Filtrado de la tabla
  function filterTable() {
    const searchText = document.getElementById('search').value.toLowerCase();
    const linea = document.getElementById('linea').value;
    const tipo = document.getElementById('tipo').value;
    const estado = document.getElementById('estado').value;

    const table = document.getElementById('inventory-table');
    const rows = table.tBodies[0].rows;

    for (let i = 0; i < rows.length; i++) {
      const cells = rows[i].cells;
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
