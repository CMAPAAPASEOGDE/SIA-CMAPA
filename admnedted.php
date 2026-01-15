<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
if ((int)($_SESSION['rol'] ?? 0) !== 1) { header("Location: acceso_denegado.php"); exit(); }

/* ---------- Entrada ---------- */
$idCodigo = (int)($_GET['idCodigo'] ?? ($_POST['idCodigo'] ?? 0));
$successMsg = '';
$errorMsg   = '';

/* ---------- Conexión ---------- */
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
  "Database" => "db_sia",
  "Uid"      => "cmapADMIN",
  "PWD"      => "@siaADMN56*",
  "Encrypt"  => true,
  "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) { die("Error de conexión: ".print_r(sqlsrv_errors(), true)); }

/* =========================================================
   1) GUARDAR CAMBIOS (POST)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
  // Sanitizar / normalizar
  $codigo        = trim((string)($_POST['codigo'] ?? ''));
  $tipo          = trim((string)($_POST['tipo'] ?? ''));
  $descripcion   = trim((string)($_POST['descripcion'] ?? ''));
  $linea         = trim((string)($_POST['linea'] ?? ''));
  $sublinea      = trim((string)($_POST['sublinea'] ?? ''));
  $unidad        = trim((string)($_POST['unidad'] ?? ''));
  $puntoReorden  = (int)($_POST['puntoReorden'] ?? 0);
  $stockMaximo   = (int)($_POST['stockMaximo'] ?? 0);

  // precio: admitir "9,500.25" o "9500,25"
  $precioStr     = str_replace([' ', ','], ['', '.'], (string)($_POST['precio'] ?? '0'));
  $precio        = (float)$precioStr;

  // Validaciones básicas
  if ($idCodigo <= 0) {
    $errorMsg = "Producto inválido.";
  } elseif ($codigo === '' || $descripcion === '' || $unidad === '' || $tipo === '') {
    $errorMsg = "Código, nombre (descripción), unidad y tipo son obligatorios.";
  } elseif ($puntoReorden < 0 || $stockMaximo < 0 || $precio < 0) {
    $errorMsg = "Valores numéricos no pueden ser negativos.";
  } else {
    // Checar duplicado de código (excluyendo el propio idCodigo)
    $stmtDup = sqlsrv_query(
      $conn,
      "SELECT COUNT(*) AS c FROM Productos WHERE codigo = ? AND idCodigo <> ?",
      [$codigo, $idCodigo]
    );
    if ($stmtDup === false) {
      $errorMsg = "Error validando código duplicado: ".print_r(sqlsrv_errors(), true);
    } else {
      $dup = (int)(sqlsrv_fetch_array($stmtDup, SQLSRV_FETCH_ASSOC)['c'] ?? 0);
      sqlsrv_free_stmt($stmtDup);

      if ($dup > 0) {
        $errorMsg = "El código ya existe en otro producto.";
      } else {
        // Actualizar
        $sqlUpd = "UPDATE Productos
                      SET codigo = ?, descripcion = ?, linea = ?, sublinea = ?,
                          unidad = ?, precio = ?, puntoReorden = ?, stockMaximo = ?, tipo = ?
                    WHERE idCodigo = ?";
        $params = [$codigo, $descripcion, $linea, $sublinea, $unidad, $precio, $puntoReorden, $stockMaximo, $tipo, $idCodigo];
        $ok = sqlsrv_query($conn, $sqlUpd, $params);

        if ($ok === false) {
          $errorMsg = "No se pudo guardar: ".print_r(sqlsrv_errors(), true);
        } else {
          $successMsg = "Cambios guardados correctamente.";
        }
      }
    }
  }
}

/* =========================================================
   2) LEER PRODUCTO (para pre-llenar después del GET o POST)
   ========================================================= */
$producto = null;
$estado   = null;

if ($idCodigo > 0) {
  $stmtProd = sqlsrv_query(
    $conn,
    "SELECT p.idCodigo, p.codigo, p.descripcion, p.linea, p.sublinea, p.unidad, p.precio,
            p.puntoReorden, p.stockMaximo, p.tipo, i.CantidadActual AS cantidad
       FROM Productos p
  LEFT JOIN Inventario i ON i.idCodigo = p.idCodigo
      WHERE p.idCodigo = ?",
    [$idCodigo]
  );
  if ($stmtProd && ($producto = sqlsrv_fetch_array($stmtProd, SQLSRV_FETCH_ASSOC))) {
    $cant  = (int)($producto['cantidad'] ?? 0);
    $reord = (int)($producto['puntoReorden'] ?? 0);
    $max   = (int)($producto['stockMaximo'] ?? 0);
    if ($cant <= 0)                       $estado = 'Fuera de stock';
    elseif ($cant <= $reord)              $estado = 'Bajo stock';
    elseif ($max > 0 && $cant >= $max)    $estado = 'Sobre stock';
    else                                  $estado = 'En stock';
  } else {
    sqlsrv_free_stmt($stmtProd);
    sqlsrv_close($conn);
    header("Location: admnedtedsrch.php?e=notfound");
    exit();
  }
  if ($stmtProd) sqlsrv_free_stmt($stmtProd);
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

/* Helpers */
function sel($a,$b){ return ($a===$b)?'selected':''; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<!DOCTYPE html>

<html>
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
  <title>SIA Admin Elements Edition Edit</title>
  <link rel="stylesheet" href="css/StyleADEDED.css">
  <style>
    .flash-ok{background:#e7f7ec;color:#1a7f37;border:1px solid #b7e1c1;border-radius:8px;padding:10px;margin:0 0 14px}
    .flash-err{background:#fdecec;color:#b42318;border:1px solid #f2b8b5;border-radius:8px;padding:10px;margin:0 0 14px}
    .notification-container{position:relative}
    .notification-dropdown{display:none;position:absolute;right:0;top:40px;background:#fff;border:1px solid #e5e5e5;border-radius:10px;width:320px;box-shadow:0 10px 25px rgba(0,0,0,.08);z-index:20}
    .notif-item{padding:8px 10px;cursor:pointer;border-bottom:1px solid #eaeaea}
    .notif-item:hover{background:#fafafa}
  </style>
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

<main class="main-container">
  <h1 class="titulo-seccion">EDITAR ELEMENTOS</h1>

  <section class="contenedor-formulario">
    <h2 class="subtitulo">EDITAR DETALLES DE LOS ELEMENTOS</h2>

    <?php if ($successMsg): ?><div class="flash-ok"><?= h($successMsg) ?></div><?php endif; ?>
    <?php if ($errorMsg):   ?><div class="flash-err"><?= h($errorMsg) ?></div><?php endif; ?>

    <form class="grid-form" method="post" action="admnedted.php">
      <input type="hidden" name="idCodigo" value="<?= (int)($producto['idCodigo'] ?? 0) ?>">

      <!-- Fila 1 -->
      <label>CÓDIGO
        <input type="text" name="codigo" value="<?= h($producto['codigo'] ?? '') ?>" required />
      </label>
      <label>TIPO
        <select name="tipo" required>
          <option value="Herramienta" <?= sel(($producto['tipo'] ?? ''), 'Herramienta') ?>>Herramienta</option>
          <option value="Materiales"   <?= sel(($producto['tipo'] ?? ''), 'Materiales') ?>>Materiales</option>
        </select>
      </label>

      <!-- Fila 2 -->
      <label>NOMBRE
        <input type="text" name="descripcion" value="<?= h($producto['descripcion'] ?? '') ?>" required />
      </label>
      <label>LÍNEA
        <input type="text" name="linea" value="<?= h($producto['linea'] ?? '') ?>" />
      </label>
      <label>SUBLÍNEA
        <input type="text" name="sublinea" value="<?= h($producto['sublinea'] ?? '') ?>" />
      </label>

      <!-- Fila 3 -->
      <label>PROVEEDOR
        <select name="proveedor">
          <option value="">— No configurado —</option>
        </select>
      </label>
      <label>PRECIO
        <input type="number" step="0.01" name="precio" value="<?= h(number_format((float)($producto['precio'] ?? 0), 2, '.', '')) ?>" min="0" />
      </label>
      <label>ESTATUS
        <select name="estatus" disabled>
          <option <?= sel($estado, 'Fuera de stock') ?>>Fuera de stock</option>
          <option <?= sel($estado, 'Bajo stock')   ?>>Bajo stock</option>
          <option <?= sel($estado, 'En stock')     ?>>En stock</option>
          <option <?= sel($estado, 'Sobre stock')  ?>>Sobre stock</option>
        </select>
      </label>

      <!-- Fila 4 -->
      <label>UNIDAD
        <select name="unidad" required>
          <?php
            $unidad = (string)($producto['unidad'] ?? '');
            $opts = ['Piezas','Kg','M'];
            if ($unidad && !in_array($unidad, $opts, true)) {
              echo '<option value="'.h($unidad).'" selected>'.h($unidad).'</option>';
            }
            foreach ($opts as $u) {
              echo '<option value="'.$u.'" '.sel($unidad,$u).'>'.$u.'</option>';
            }
          ?>
        </select>
      </label>
      <label>PUNTO REORDEN
        <input type="number" name="puntoReorden" value="<?= (int)($producto['puntoReorden'] ?? 0) ?>" min="0" />
      </label>
      <label>STOCK MÁXIMO
        <input type="number" name="stockMaximo" value="<?= (int)($producto['stockMaximo'] ?? 0) ?>" min="0" />
      </label>

      <div class="botones-formulario">
        <a href="admnedt.php"><button type="button" class="boton-negro">CANCELAR</button></a>
        <button type="submit" name="guardar" value="1" class="boton-negro">CONFIRMAR</button>
      </div>
    </form>
  </section>
</main>

<script src="js/notificaciones.js"></script>
<script src="js/menus.js"></script>

</body>
</html>
