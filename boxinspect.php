<?php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1, 2], true)) {
    header("Location: acceso_denegado.php");
    exit();
}

// --- Param de caja ---
$idCaja = isset($_GET['idCaja']) ? (int)$_GET['idCaja'] : 0;
if ($idCaja <= 0) {
    error_log("ID de caja inválido: " . ($_GET['idCaja'] ?? 'N/A'));
    header("Location: boxes.php?error=invalid_id");
    exit();
}

// --- Conexión BD (una sola vez) ---
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

/* ===============================
   POST actions (update/delete)
   =============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1) Cambiar responsable (solo admin)
    if (isset($_POST['accion']) && $_POST['accion'] === 'cambiar_responsable' && $idRol === 1) {
        $nuevoId = (int)($_POST['nuevo_responsable'] ?? 0);
        if ($nuevoId > 0) {
            $updateSQL = "UPDATE CajaRegistro SET idOperador = ? WHERE idCaja = ?";
            $stmtUpdate = sqlsrv_query($conn, $updateSQL, [$nuevoId, $idCaja]);
            if ($stmtUpdate) {
                echo "<script>alert('Responsable actualizado correctamente'); location.href='boxinspect.php?idCaja={$idCaja}';</script>";
                exit();
            } else {
                echo "<script>alert('Error al actualizar responsable');</script>";
            }
        }
    }

    // 2) Eliminar caja (solo admin)
    elseif (isset($_POST['accion']) && $_POST['accion'] === 'eliminar_caja' && $idRol === 1) {
        sqlsrv_begin_transaction($conn);
        try {
            // items dentro de la caja
            $sqlGetItems = "SELECT idCodigo, cantidad FROM CajaContenido WHERE idCaja = ?";
            $stmtGetItems = sqlsrv_query($conn, $sqlGetItems, [$idCaja]);
            if ($stmtGetItems === false) {
                throw new Exception("Error al obtener contenido: " . print_r(sqlsrv_errors(), true));
            }

            // devolver inventario
            while ($item = sqlsrv_fetch_array($stmtGetItems, SQLSRV_FETCH_ASSOC)) {
                $updateInventario = "UPDATE Inventario 
                                     SET CantidadActual = CantidadActual + ? 
                                     WHERE idCodigo = ?";
                $ok = sqlsrv_query($conn, $updateInventario, [(int)$item['cantidad'], (int)$item['idCodigo']]);
                if ($ok === false) {
                    throw new Exception("Error al actualizar inventario: " . print_r(sqlsrv_errors(), true));
                }
            }

            // borrar contenido + caja
            $ok1 = sqlsrv_query($conn, "DELETE FROM CajaContenido WHERE idCaja = ?", [$idCaja]);
            if ($ok1 === false) throw new Exception("Error al eliminar contenido: " . print_r(sqlsrv_errors(), true));

            $ok2 = sqlsrv_query($conn, "DELETE FROM CajaRegistro WHERE idCaja = ?", [$idCaja]);
            if ($ok2 === false) throw new Exception("Error al eliminar caja: " . print_r(sqlsrv_errors(), true));

            sqlsrv_commit($conn);
            header("Location: boxes.php?msg=caja_eliminada");
            exit();

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            echo "<script>alert('Error al eliminar la caja: " . addslashes($e->getMessage()) . "');</script>";
        }
    }

    // 3) Confirmar cambios en elementos
    elseif (isset($_POST['confirmar'])) {
        // contenido actual
        $contenidoActual = [];
        $stmtActual = sqlsrv_query($conn, "SELECT idCodigo, cantidad FROM CajaContenido WHERE idCaja = ?", [$idCaja]);
        if ($stmtActual === false) die("Error al obtener contenido actual: " . print_r(sqlsrv_errors(), true));
        while ($fila = sqlsrv_fetch_array($stmtActual, SQLSRV_FETCH_ASSOC)) {
            $contenidoActual[(int)$fila['idCodigo']] = (int)$fila['cantidad'];
        }

        $elementos = $_POST['elementos'] ?? [];
        $diferencias = [];
        $errorStock = false;
        $idCodigoError = 0;

        // Verificar stock cuando hay aumento
        foreach ($elementos as $elem) {
            $idCodigo = (int)($elem['idCodigo'] ?? 0);
            $cantidad = (int)($elem['cantidad'] ?? 0);
            if ($idCodigo > 0 && $cantidad >= 0) {
                $antes = $contenidoActual[$idCodigo] ?? 0;
                $diff  = $cantidad - $antes;
                if ($diff > 0) {
                    $stmtStock = sqlsrv_query($conn, "SELECT CantidadActual FROM Inventario WHERE idCodigo = ?", [$idCodigo]);
                    if ($stmtStock === false) die("Error al verificar stock: " . print_r(sqlsrv_errors(), true));
                    $rowStock = sqlsrv_fetch_array($stmtStock, SQLSRV_FETCH_ASSOC);
                    if (!$rowStock || (int)$rowStock['CantidadActual'] < $diff) {
                        $errorStock = true; $idCodigoError = $idCodigo; break;
                    }
                }
            }
        }

        if ($errorStock) {
            header("Location: boxinspect.php?idCaja={$idCaja}&error=stock&idCodigo=" . $idCodigoError);
            exit();
        }

        sqlsrv_begin_transaction($conn);
        try {
            foreach ($elementos as $elem) {
                $idCodigo = (int)($elem['idCodigo'] ?? 0);
                $cantidad = (int)($elem['cantidad'] ?? 0);
                if ($idCodigo > 0 && $cantidad >= 0) {
                    $antes = $contenidoActual[$idCodigo] ?? 0;
                    $diff  = $cantidad - $antes;

                    if ($diff != 0) {
                        $diferencias[$idCodigo] = ($diferencias[$idCodigo] ?? 0) + $diff;
                    }

                    // existe en caja?
                    $checkStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM CajaContenido WHERE idCaja = ? AND idCodigo = ?", [$idCaja, $idCodigo]);
                    if ($checkStmt === false) throw new Exception("Error al verificar existencia: " . print_r(sqlsrv_errors(), true));
                    $countRow = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);

                    if ((int)$countRow['total'] > 0) {
                        $ok = sqlsrv_query($conn, "UPDATE CajaContenido SET cantidad = ? WHERE idCaja = ? AND idCodigo = ?", [$cantidad, $idCaja, $idCodigo]);
                    } else {
                        $ok = sqlsrv_query($conn, "INSERT INTO CajaContenido (idCaja, idCodigo, cantidad) VALUES (?, ?, ?)", [$idCaja, $idCodigo, $cantidad]);
                    }
                    if ($ok === false) throw new Exception("Error al actualizar contenido: " . print_r(sqlsrv_errors(), true));
                }
            }

            // actualizar inventario con las diferencias (resta cuando agregas a la caja / suma cuando retiras)
            foreach ($diferencias as $idCod => $diff) {
                $ok = sqlsrv_query($conn, "UPDATE Inventario SET CantidadActual = CantidadActual - ? WHERE idCodigo = ?", [$diff, $idCod]);
                if ($ok === false) throw new Exception("Error al actualizar inventario: " . print_r(sqlsrv_errors(), true));
            }

            sqlsrv_commit($conn);
            header("Location: boxinspectcnf.php?idCaja={$idCaja}");
            exit();

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            die("Error: " . $e->getMessage());
        }
    }
}

/* ===============================
   Datos de la caja + contenido
   =============================== */
$stmtCaja = sqlsrv_query($conn, "SELECT C.numeroCaja, O.nombreCompleto AS nombreOperador 
                                 FROM CajaRegistro C
                                 JOIN Operativo O ON C.idOperador = O.idOperador
                                 WHERE C.idCaja = ?", [$idCaja]);
if ($stmtCaja === false) die("Error al obtener datos de caja: " . print_r(sqlsrv_errors(), true));
$datosCaja = sqlsrv_fetch_array($stmtCaja, SQLSRV_FETCH_ASSOC);
if (!$datosCaja) { header("Location: boxes.php?error=caja_not_found"); exit(); }

$numeroCaja     = $datosCaja['numeroCaja']     ?? '---';
$nombreOperador = $datosCaja['nombreOperador'] ?? 'SIN OPERADOR';

// Contenido de la caja a arreglo
$contenidoRows = [];
$stmtContenido = sqlsrv_query($conn, "SELECT cc.idCodigo, p.codigo AS codigoProducto, p.descripcion, cc.cantidad
                                      FROM CajaContenido cc
                                      JOIN Productos p ON cc.idCodigo = p.idCodigo
                                      WHERE cc.idCaja = ?", [$idCaja]);
if ($stmtContenido === false) die("Error al obtener contenido: " . print_r(sqlsrv_errors(), true));
while ($row = sqlsrv_fetch_array($stmtContenido, SQLSRV_FETCH_ASSOC)) {
    $contenidoRows[] = $row;
}

/* ===============================
   NOTIFICACIONES (header nuevo)
   =============================== */
$rolActual   = (int)($_SESSION['rol'] ?? 0);
$unreadCount = 0;
$notifList   = [];

// Admin: desde Modificaciones (pendientes)
if ($rolActual === 1) {
    $stmtCount = sqlsrv_query($conn,
        "SELECT COUNT(*) AS c
           FROM Modificaciones
          WHERE solicitudRevisada = 0");

    $stmtList  = sqlsrv_query($conn,
        "SELECT TOP 10
                M.idModificacion,
                M.descripcion,
                M.fechaSolicitud,
                M.tipo,
                M.cantidad,
                P.codigo AS codigoProducto
           FROM Modificaciones M
      LEFT JOIN Productos P ON P.idCodigo = M.idCodigo
          WHERE M.solicitudRevisada = 0
       ORDER BY M.fechaSolicitud DESC");
}
// Usuario (rol 2): desde Notificaciones (no leídas)
else {
    $stmtCount = sqlsrv_query($conn,
        "SELECT COUNT(*) AS c
           FROM Notificaciones
          WHERE estatusRevision = 0");

    $stmtList  = sqlsrv_query($conn,
        "SELECT TOP 10
                N.idNotificacion,
                N.descripcion      AS comentarioAdmin,
                N.fechaNotificacion,
                P.codigo           AS codigoProducto
           FROM Notificaciones N
      LEFT JOIN Modificaciones M ON M.idModificacion = N.idModificacion
      LEFT JOIN Productos      P ON P.idCodigo       = M.idCodigo
          WHERE N.estatusRevision = 0
       ORDER BY N.fechaNotificacion DESC");
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

// (cerramos al final del documento)
// sqlsrv_close($conn);
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
          sqlsrv_free_stmt($stmtOp);
          ?>
        </select>
        <button type="submit" name="accion" value="cambiar_responsable" class="btn-secundario">CAMBIAR RESPONSABLE</button>
      </form>
    <?php endif; ?>
  </section>

  <form method="POST" id="form-update">
    <section class="elementos-section" id="elementos-container">
      <div class="elementos-header">
        <span>CÓDIGO</span>
        <span>CONTENIDO</span>
        <span>CANTIDAD</span>
      </div>

      <?php 
      $index = 0;
      if (empty($contenidoRows)) {
          echo '<div class="error-container">No se encontraron elementos en esta caja</div>';
      } else {
          foreach ($contenidoRows as $row) { ?>
            <div class="elemento-row" id="elem-<?= $index ?>">
              <input type="hidden" name="elementos[<?= $index ?>][idCodigo]" value="<?= (int)$row['idCodigo'] ?>">
              <input type="text" value="<?= htmlspecialchars($row['codigoProducto']) ?>" readonly>
              <input type="text" value="<?= htmlspecialchars($row['descripcion']) ?>" readonly>
              <input type="number" name="elementos[<?= $index ?>][cantidad]" id="cant-<?= $index ?>" value="<?= (int)$row['cantidad'] ?>" min="0">
              <button type="button" onclick="eliminarElemento(<?= $index ?>)" style="background-color: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer;">✕</button>
            </div>
          <?php
            $index++;
          }
      } ?>

      <div class="nuevos-elementos" id="nuevos-elementos"></div>
    </section>
  </form>

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
  nuevoDiv.setAttribute('id', 'elem-' + contador);
  nuevoDiv.innerHTML = `
    <select name="elementos[${contador}][idCodigo]" onchange="cargarNombre(this)" class="codigo-select">
      <option value="">Seleccionar código</option>
      <?php
      $stmtProds = sqlsrv_query($conn, "SELECT idCodigo, codigo, descripcion FROM Productos ORDER BY codigo ASC");
      while ($prod = sqlsrv_fetch_array($stmtProds, SQLSRV_FETCH_ASSOC)) {
          $id = (int)$prod['idCodigo'];
          $cod = htmlspecialchars($prod['codigo'], ENT_QUOTES, 'UTF-8');
          $desc = htmlspecialchars($prod['descripcion'], ENT_QUOTES, 'UTF-8');
          echo "<option value='{$id}' data-descripcion=\"{$desc}\">{$cod} - {$desc}</option>";
      }
      sqlsrv_free_stmt($stmtProds);
      ?>
    </select>
    <input type="text" name="elementos[${contador}][nombre]" placeholder="NOMBRE" readonly>
    <input type="number" name="elementos[${contador}][cantidad]" placeholder="CANTIDAD" min="1" required>
    <button type="button" onclick="eliminarElemento(${contador})" style="background-color: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer;">✕</button>
  `;
  contenedor.appendChild(nuevoDiv);
  contador++;
}

function eliminarElemento(index) {
  const elemento = document.getElementById('elem-' + index);
  if (elemento) {
    // Para elementos existentes, poner cantidad en 0 en lugar de eliminar el div
    const cantInput = document.getElementById('cant-' + index);
    if (cantInput) {
      cantInput.value = 0;
      elemento.style.display = 'none';
    } else {
      // Para elementos nuevos, eliminar completamente
      elemento.remove();
    }
  }
}
function cargarNombre(select) {
  const descripcion = select.options[select.selectedIndex].getAttribute('data-descripcion') || '';
  const inputNombre = select.nextElementSibling;
  inputNombre.value = descripcion;
}
</script>

<script>
const toggle = document.getElementById('menu-toggle');
const dropdown = document.getElementById('dropdown-menu');
if (toggle && dropdown) {
  toggle.addEventListener('click', () => {
    dropdown.style.display = dropdown.style.display === 'flex' ? 'none' : 'flex';
  });
  window.addEventListener('click', (e) => {
    if (!toggle.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
  });
}
const userToggle = document.getElementById('user-toggle');
const userDropdown = document.getElementById('user-dropdown');
if (userToggle && userDropdown) {
  userToggle.addEventListener('click', () => {
    userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
  });
  window.addEventListener('click', (e) => {
    if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) userDropdown.style.display = 'none';
  });
}
const notifToggle = document.getElementById('notif-toggle');
const notifDropdown = document.getElementById('notif-dropdown');
if (notifToggle && notifDropdown) {
  notifToggle.addEventListener('click', () => {
    notifDropdown.style.display = notifDropdown.style.display === 'block' ? 'none' : 'block';
  });
  window.addEventListener('click', (e) => {
    if (!notifToggle.contains(e.target) && !notifDropdown.contains(e.target)) notifDropdown.style.display = 'none';
  });
}

// Confirmar lectura (rol 2)
function ackUserNotif(idNotificacion) {
  fetch('php/ack_user_notif.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
    body: 'id=' + encodeURIComponent(idNotificacion)
  }).then(r => r.json()).catch(() => ({}))
    .finally(() => { window.location.href = 'boxinspect.php?idCaja=<?= $idCaja ?>'; });
}

function confirmarEliminacion() {
  return confirm("¿Estás seguro de que deseas eliminar esta caja? Esta acción no se puede deshacer.");
}
</script>
<?php sqlsrv_close($conn); ?>
</body>
</html>
