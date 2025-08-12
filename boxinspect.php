<?php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1, 2])) {
    header("Location: acceso_denegado.php");
    exit();
}

// Obtener el ID de la caja desde la URL
$idCaja = isset($_GET['idCaja']) ? intval($_GET['idCaja']) : 0;
if ($idCaja <= 0) {
    error_log("ID de caja inválido: " . ($_GET['idCaja'] ?? 'N/A'));
    header("Location: boxes.php?error=invalid_id");
    exit();
}

// Conectar a la BD
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

// Manejar todas las acciones POST en un solo lugar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Cambio de responsable
    if (isset($_POST['accion']) && $_POST['accion'] === 'cambiar_responsable' && $idRol === 1) {
        $nuevoId = intval($_POST['nuevo_responsable'] ?? 0);
        if ($nuevoId > 0) {
            $updateSQL = "UPDATE CajaRegistro SET idOperador = ? WHERE idCaja = ?";
            $stmtUpdate = sqlsrv_query($conn, $updateSQL, [$nuevoId, $idCaja]);
            if ($stmtUpdate) {
                echo "<script>alert('Responsable actualizado correctamente'); location.href='boxinspect.php?idCaja=$idCaja';</script>";
                exit();
            } else {
                echo "<script>alert('Error al actualizar responsable');</script>";
            }
        }
    }
    
    // 2. Eliminación de caja
    elseif (isset($_POST['accion']) && $_POST['accion'] === 'eliminar_caja' && $idRol === 1) {
        sqlsrv_begin_transaction($conn);
        try {
            // Obtener elementos para devolver al inventario
            $sqlGetItems = "SELECT idCodigo, cantidad FROM CajaContenido WHERE idCaja = ?";
            $stmtGetItems = sqlsrv_query($conn, $sqlGetItems, [$idCaja]);
            
            if ($stmtGetItems === false) {
                throw new Exception("Error al obtener contenido: " . print_r(sqlsrv_errors(), true));
            }
            
            // Devolver productos al inventario
            while ($item = sqlsrv_fetch_array($stmtGetItems, SQLSRV_FETCH_ASSOC)) {
                $updateInventario = "UPDATE Inventario 
                                    SET CantidadActual = CantidadActual + ? 
                                    WHERE idCodigo = ?";
                $params = [$item['cantidad'], $item['idCodigo']];
                $stmtUpdate = sqlsrv_query($conn, $updateInventario, $params);
                
                if ($stmtUpdate === false) {
                    throw new Exception("Error al actualizar inventario: " . print_r(sqlsrv_errors(), true));
                }
            }
            
            // Eliminar contenido de la caja
            $deleteContenido = "DELETE FROM CajaContenido WHERE idCaja = ?";
            $stmtDelete = sqlsrv_query($conn, $deleteContenido, [$idCaja]);
            
            if ($stmtDelete === false) {
                throw new Exception("Error al eliminar contenido: " . print_r(sqlsrv_errors(), true));
            }
            
            // Eliminar la caja
            $deleteCaja = "DELETE FROM CajaRegistro WHERE idCaja = ?";
            $stmtDelete = sqlsrv_query($conn, $deleteCaja, [$idCaja]);
            
            if ($stmtDelete === false) {
                throw new Exception("Error al eliminar caja: " . print_r(sqlsrv_errors(), true));
            }
            
            sqlsrv_commit($conn);
            header("Location: boxes.php?msg=caja_eliminada");
            exit();
            
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            echo "<script>alert('Error al eliminar la caja: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
    
    // 3. Confirmar cambios en elementos
    elseif (isset($_POST['confirmar'])) {
        // Obtener contenido actual ANTES de los cambios
        $contenidoActual = [];
        $sqlActual = "SELECT idCodigo, cantidad FROM CajaContenido WHERE idCaja = ?";
        $stmtActual = sqlsrv_query($conn, $sqlActual, [$idCaja]);
        
        if ($stmtActual === false) {
            die("Error al obtener contenido actual: " . print_r(sqlsrv_errors(), true));
        }
        
        while ($fila = sqlsrv_fetch_array($stmtActual, SQLSRV_FETCH_ASSOC)) {
            $contenidoActual[$fila['idCodigo']] = $fila['cantidad'];
        }

        $elementos = $_POST['elementos'] ?? [];
        $diferencias = []; // Para almacenar cambios en cantidades
        $errorStock = false;
        $idCodigoError = 0;

        // Primera pasada: Verificar disponibilidad de stock
        foreach ($elementos as $elem) {
            $idCodigo = intval($elem['idCodigo'] ?? 0);
            $cantidad = intval($elem['cantidad'] ?? 0);

            if ($idCodigo > 0 && $cantidad >= 0) {
                $cantidadAnterior = $contenidoActual[$idCodigo] ?? 0;
                $diferencia = $cantidad - $cantidadAnterior;

                // Solo verificar stock cuando se agregan productos (diferencia positiva)
                if ($diferencia > 0) {
                    // Verificar stock disponible
                    $sqlStock = "SELECT CantidadActual FROM Inventario WHERE idCodigo = ?";
                    $stmtStock = sqlsrv_query($conn, $sqlStock, [$idCodigo]);
                    
                    if ($stmtStock === false) {
                        die("Error al verificar stock: " . print_r(sqlsrv_errors(), true));
                    }
                    
                    $rowStock = sqlsrv_fetch_array($stmtStock, SQLSRV_FETCH_ASSOC);
                    
                    if (!$rowStock || $rowStock['CantidadActual'] < $diferencia) {
                        $errorStock = true;
                        $idCodigoError = $idCodigo;
                        break;
                    }
                }
            }
        }

        // Si hay error de stock, redirigir
        if ($errorStock) {
            header("Location: boxinspecter.php?idCaja=" . $idCaja . "&error=stock&idCodigo=" . $idCodigoError);
            exit();
        }

        // Iniciar transacción para operaciones atómicas
        sqlsrv_begin_transaction($conn);

        try {
            // Segunda pasada: Procesar cambios
            foreach ($elementos as $elem) {
                $idCodigo = intval($elem['idCodigo'] ?? 0);
                $cantidad = intval($elem['cantidad'] ?? 0);

                if ($idCodigo > 0 && $cantidad >= 0) {
                    $cantidadAnterior = $contenidoActual[$idCodigo] ?? 0;
                    $diferencia = $cantidad - $cantidadAnterior;

                    // Almacenar diferencia para actualizar inventario
                    if ($diferencia != 0) {
                        $diferencias[$idCodigo] = ($diferencias[$idCodigo] ?? 0) + $diferencia;
                    }

                    // Verificar si ya existe en la caja
                    $checkSql = "SELECT COUNT(*) AS total FROM CajaContenido WHERE idCaja = ? AND idCodigo = ?";
                    $checkStmt = sqlsrv_query($conn, $checkSql, [$idCaja, $idCodigo]);
                    
                    if ($checkStmt === false) {
                        throw new Exception("Error al verificar existencia: " . print_r(sqlsrv_errors(), true));
                    }
                    
                    $checkRow = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);

                    if ($checkRow['total'] > 0) {
                        // Actualizar cantidad
                        $updateSql = "UPDATE CajaContenido SET cantidad = ? WHERE idCaja = ? AND idCodigo = ?";
                        $result = sqlsrv_query($conn, $updateSql, [$cantidad, $idCaja, $idCodigo]);
                    } else {
                        // Insertar nuevo registro
                        $insertSql = "INSERT INTO CajaContenido (idCaja, idCodigo, cantidad) VALUES (?, ?, ?)";
                        $result = sqlsrv_query($conn, $insertSql, [$idCaja, $idCodigo, $cantidad]);
                    }
                    
                    if ($result === false) {
                        throw new Exception("Error al actualizar contenido: " . print_r(sqlsrv_errors(), true));
                    }
                }
            }
            
            // Actualizar inventario con las diferencias
            foreach ($diferencias as $idCodigo => $diferencia) {
                $updateInventario = "UPDATE Inventario 
                                    SET CantidadActual = CantidadActual - ? 
                                    WHERE idCodigo = ?";
                $result = sqlsrv_query($conn, $updateInventario, [$diferencia, $idCodigo]);
                
                if ($result === false) {
                    throw new Exception("Error al actualizar inventario: " . print_r(sqlsrv_errors(), true));
                }
            }

            // Confirmar todas las operaciones
            sqlsrv_commit($conn);
            
            // Redirigir a página de confirmación
            header("Location: boxinspectcnf.php?idCaja=" . $idCaja);
            exit();

        } catch (Exception $e) {
            // Revertir todas las operaciones en caso de error
            sqlsrv_rollback($conn);
            die("Error: " . $e->getMessage());
        }
    }
}

// Obtener datos de la caja
$sqlCaja = "SELECT C.numeroCaja, O.nombreCompleto AS nombreOperador 
            FROM CajaRegistro C
            INNER JOIN Operativo O ON C.idOperador = O.idOperador
            WHERE C.idCaja = ?";
$params = [$idCaja];
$stmtCaja = sqlsrv_query($conn, $sqlCaja, $params);

if ($stmtCaja === false) {
    die("Error al obtener datos de caja: " . print_r(sqlsrv_errors(), true));
}

$datosCaja = sqlsrv_fetch_array($stmtCaja, SQLSRV_FETCH_ASSOC);

if (!$datosCaja) {
    header("Location: boxes.php?error=caja_not_found");
    exit();
}

$numeroCaja = $datosCaja['numeroCaja'] ?? '---';
$nombreOperador = $datosCaja['nombreOperador'] ?? 'SIN OPERADOR';

// Obtener contenido de la caja (con código de producto)
$sqlContenido = "SELECT cc.idCodigo, p.codigo AS codigoProducto, p.descripcion, cc.cantidad
                 FROM CajaContenido cc
                 INNER JOIN Productos p ON cc.idCodigo = p.idCodigo
                 WHERE cc.idCaja = ?";
$stmtContenido = sqlsrv_query($conn, $sqlContenido, $params);

if ($stmtContenido === false) {
    die("Error al obtener contenido: " . print_r(sqlsrv_errors(), true));
}

$rolActual   = (int)($_SESSION['rol'] ?? 0);
$notifTarget = ($rolActual === 1) ? 'admnrqst.php' : 'mis_notifs.php';

$unreadCount = 0;
$notifList   = [];

if ($conn) {
    if ($rolActual === 1) {
        // ADMIN: ver SOLO las destinadas a admin (idRol = 1)
        $stmtCount = sqlsrv_query($conn, "SELECT COUNT(*) AS c FROM Notificaciones WHERE solicitudRevisada = 0 AND idRol = 1");
        $stmtList  = sqlsrv_query($conn, "SELECT TOP 10 idNotificacion, descripcion, fecha
                                          FROM Notificaciones
                                          WHERE solicitudRevisada = 0 AND idRol = 1
                                          ORDER BY fecha DESC");
    } else {
        // USUARIO: ver SOLO las destinadas a su rol (p. ej. 2)
        $stmtCount = sqlsrv_query($conn, "SELECT COUNT(*) AS c FROM Notificaciones WHERE solicitudRevisada = 0 AND idRol = ?", [$rolActual]);
        $stmtList  = sqlsrv_query($conn, "SELECT TOP 10 idNotificacion, descripcion, fecha
                                          FROM Notificaciones
                                          WHERE solicitudRevisada = 0 AND idRol = ?
                                          ORDER BY fecha DESC", [$rolActual]);
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

// Reiniciar el puntero del resultado para asegurar que podamos recorrerlo
sqlsrv_fetch($stmtContenido, SQLSRV_SCROLL_FIRST);
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Toolbox Inspection</title>
    <link rel="stylesheet" href="css/StyleBXIP.css">
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

<main class="caja-gestion-container">
    <div class="caja-gestion-title">
        <h2>CAJA</h2>
        <div class="caja-numero">CAJA <?= htmlspecialchars($numeroCaja) ?></div>
    </div>

    <section class="responsable-section">
        <label for="responsable">RESPONSABLE</label>
        <input type="text" id="responsable" value="<?= htmlspecialchars($nombreOperador) ?>" readonly>

        <?php if ($idRol === 1): ?>
            <form method="POST" class="cambiar-responsable-form">
                <select name="nuevo_responsable" required>
                    <option value="">Seleccionar nuevo responsable</option>
                    <?php
                    $stmtOp = sqlsrv_query($conn, "SELECT idOperador, nombreCompleto FROM Operativo");
                    while ($op = sqlsrv_fetch_array($stmtOp, SQLSRV_FETCH_ASSOC)) {
                        echo '<option value="'.$op['idOperador'].'">'.htmlspecialchars($op['nombreCompleto']).'</option>';
                    }
                    ?>
                </select>
                <button type="submit" name="accion" value="cambiar_responsable" class="btn-secundario">CAMBIAR RESPONSABLE</button>
            </form>
        <?php endif; ?>
    </section>

    <!-- Formulario para actualizar elementos -->
    <form method="POST" id="form-update">
        <section class="elementos-section" id="elementos-container">
            <div class="elementos-header">
                <span>CÓDIGO</span>
                <span>CONTENIDO</span>
                <span>CANTIDAD</span>
            </div>

            <?php 
            $index = 0;
            $elementosEncontrados = false;

            // Reiniciar el puntero del resultado para asegurar que podamos recorrerlo
            sqlsrv_fetch($stmtContenido, SQLSRV_SCROLL_FIRST);
            
            while ($row = sqlsrv_fetch_array($stmtContenido, SQLSRV_FETCH_ASSOC)) {
                $elementosEncontrados = true;
                ?>
                <div class="elemento-row">
                    <input type="hidden" name="elementos[<?= $index ?>][idCodigo]" 
                           value="<?= htmlspecialchars($row['idCodigo']) ?>">
                    <input type="text" value="<?= htmlspecialchars($row['codigoProducto']) ?>" readonly>
                    <input type="text" value="<?= htmlspecialchars($row['descripcion']) ?>" readonly>
                    <input type="number" name="elementos[<?= $index ?>][cantidad]" 
                           value="<?= htmlspecialchars($row['cantidad']) ?>" min="0">
                </div>
                <?php
                $index++;
            }

            if (!$elementosEncontrados) {
                echo '<div class="error-container">No se encontraron elementos en esta caja</div>';
            }
            ?>

            <div class="nuevos-elementos" id="nuevos-elementos">
                <!-- Los nuevos elementos se añadirán aquí dinámicamente -->
            </div>
        </section>
    </form>

    <!-- Formulario separado para eliminar la caja -->
    <?php if ($idRol === 1): ?>
        <form method="POST" id="form-delete" onsubmit="return confirmarEliminacion()">
            <input type="hidden" name="accion" value="eliminar_caja">
        </form>
    <?php endif; ?>

    <div class="caja-gestion-actions">
        <button type="button" class="btn-secundario" onclick="agregarElemento()">AÑADIR NUEVO ELEMENTO</button>
        
        <?php if ($idRol === 1): ?>
            <button type="submit" form="form-delete" class="btn-secundario">BORRAR LA CAJA</button>
        <?php endif; ?>
        
        <a href="boxes.php"><button type="button" class="btn">CANCELAR</button></a>
        <button type="submit" form="form-update" class="btn" name="confirmar">CONFIRMAR</button>
    </div>
</main>

<script>
let contador = <?= $index ?>;

function agregarElemento() {
    const contenedor = document.getElementById('nuevos-elementos');
    const nuevoDiv = document.createElement('div');
    nuevoDiv.classList.add('elemento-row');

    nuevoDiv.innerHTML = `
        <select name="elementos[${contador}][idCodigo]" onchange="cargarNombre(this)" class="codigo-select">
            <option value="">Seleccionar código</option>
            <?php
            $sqlProductos = "SELECT idCodigo, codigo, descripcion FROM Productos";
            $stmtProds = sqlsrv_query($conn, $sqlProductos);
            while ($prod = sqlsrv_fetch_array($stmtProds, SQLSRV_FETCH_ASSOC)) {
                $id = htmlspecialchars($prod['idCodigo']);
                $cod = htmlspecialchars($prod['codigo']);
                $desc = htmlspecialchars($prod['descripcion']);
                echo "<option value='$id' data-descripcion=\"$desc\">$cod</option>";
            }
            ?>
        </select>
        <input type="text" name="elementos[${contador}][nombre]" placeholder="NOMBRE" readonly>
        <input type="number" name="elementos[${contador}][cantidad]" placeholder="CANTIDAD" min="1" required>
    `;

    contenedor.appendChild(nuevoDiv);
    contador++;
}

function cargarNombre(select) {
    const descripcion = select.options[select.selectedIndex].getAttribute('data-descripcion');
    const inputNombre = select.nextElementSibling;
    inputNombre.value = descripcion;
}
</script>

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
function confirmarEliminacion() {
    return confirm("¿Estás seguro de que deseas eliminar esta caja? Esta acción no se puede deshacer.");
}
</script>
</body>
</html>
