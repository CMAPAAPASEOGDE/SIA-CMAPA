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

// Conexión a SQL Server
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
    die(print_r(sqlsrv_errors(), true));
}

/* =========================================
   OBTENER herramientas prestadas (enInventario = 0)
   ========================================= */
$herramientas = [];
$sql = "SELECT 
            H.idHerramienta,           -- UNIQUEIDENTIFIER (GUID)
            H.identificadorUnico,
            H.idCodigo,
            P.codigo,
            P.descripcion
        FROM HerramientasUnicas H
        INNER JOIN Productos P ON H.idCodigo = P.idCodigo
        WHERE H.enInventario = 0
        ORDER BY P.descripcion, H.identificadorUnico";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $herramientas[] = $row;
}
sqlsrv_free_stmt($stmt);

// Cerrar conexión cuando ya no la necesitemos para más consultas PHP
sqlsrv_close($conn);

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

<html lang="es">
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA Tool Devolution</title>
    <link rel="stylesheet" href="css/StyleDVTL.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

<main class="devolucion-container">
  <div class="devolucion-title">
    <h2>DEVOLUCIÓN DE HERRAMIENTAS</h2>
  </div>

  <form id="devolucionForm" class="devolucion-form">
    <!-- Identificador -->
    <label for="codigo">IDENTIFICADOR</label>
    <select id="codigo" name="idHerramienta" required>
      <option value="">-- Selecciona una herramienta prestada --</option>
      <?php foreach ($herramientas as $h): ?>
        <option
          value="<?= htmlspecialchars($h['idHerramienta'], ENT_QUOTES, 'UTF-8') ?>"
          data-ident="<?= htmlspecialchars($h['identificadorUnico'], ENT_QUOTES, 'UTF-8') ?>"
          data-idcodigo="<?= (int)$h['idCodigo'] ?>"
          data-codigo="<?= htmlspecialchars($h['codigo'], ENT_QUOTES, 'UTF-8') ?>"
          data-desc="<?= htmlspecialchars($h['descripcion'], ENT_QUOTES, 'UTF-8') ?>"
        >
          <?= htmlspecialchars($h['identificadorUnico'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($h['descripcion'], ENT_QUOTES, 'UTF-8') ?>
        </option>
      <?php endforeach; ?>
    </select>

    <!-- Nombre / Descripción -->
    <label for="nombre">NOMBRE O DESCRIPCIÓN</label>
    <input type="text" id="nombre" value="" readonly>

    <!-- Observaciones -->
    <label for="observaciones">OBSERVACIONES</label>
    <textarea id="observaciones" name="observaciones" rows="5" maxlength="200"></textarea>

    <!-- Estado y Fecha -->
    <div class="form-row">
      <div class="form-group">
        <label for="estado">ESTADO</label>
        <select id="estado" name="estado" required>
          <option value="">-- Seleccione --</option>
          <option value="NECESITA CAMBIO">NECESITA CAMBIO</option>
          <option value="FUNCIONAL">FUNCIONAL</option>
          <option value="EN REPARACIÓN">EN REPARACIÓN</option>
        </select>
      </div>
      <div class="form-group">
        <label for="fecha">FECHA DE RETORNO</label>
        <input type="date" id="fecha" name="fechaRetorno" value="<?= date('Y-m-d') ?>">
      </div>
    </div>

    <!-- Botones -->
    <div class="form-buttons">
      <a href="warehouse.php"><button type="button" class="btn cancel">CANCELAR</button></a>
      <button type="submit" class="btn confirm" id="btnConfirm" <?= empty($herramientas) ? 'disabled' : '' ?>>CONFIRMAR</button>
    </div>

    <?php if (empty($herramientas)): ?>
      <p class="hint">No hay herramientas pendientes de devolución.</p>
    <?php endif; ?>
  </form>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="js/notificaciones.js"></script>
<script src="js/menus.js"></script>

<script>
$(function () {
  // Autocompletar descripción al seleccionar
  $('#codigo').on('change', function() {
    const selected = $(this).find('option:selected');
    $('#nombre').val(selected.data('desc') || '');
  });

  // Inicializa descripción si ya hay selección
  $('#codigo').trigger('change');

  // Enviar formulario vía AJAX
  $('#devolucionForm').on('submit', function(e) {
    e.preventDefault();

    const selected = $('#codigo').find('option:selected');
    const idHerramienta = $('#codigo').val();
    const estado = $('#estado').val();

    if (!idHerramienta || !estado) {
      alert('Por favor complete los campos obligatorios');
      return;
    }

    const formData = {
      idHerramienta: idHerramienta,                       // GUID como texto
      identificadorUnico: selected.data('ident') || '',   // respaldo opcional
      observaciones: $('#observaciones').val(),
      estado: estado,
      fechaRetorno: $('#fecha').val(),
      registradoPor: <?= (int)($_SESSION['rol'] ?? 0) ?>  // <-- En tu modelo va el idRol, no el user_id
    };

    $('#btnConfirm').prop('disabled', true);

    $.ajax({
      type: 'POST',
      url: 'php/procesar_devolucion.php', // ajusta la ruta si tu script está en otra carpeta
      data: formData,
      dataType: 'json'
    }).done(function(response) {
      if (response && response.success) {
        window.location.href = 'devtlcnf.php';
      } else {
        alert('Error: ' + (response.message || 'Error desconocido'));
        $('#btnConfirm').prop('disabled', false);
      }
    }).fail(function(jqXHR, textStatus, errorThrown) {
      alert('Error en la solicitud: ' + textStatus + ', ' + errorThrown);
      $('#btnConfirm').prop('disabled', false);
    });
  });
});
</script>
</body>
</html>
