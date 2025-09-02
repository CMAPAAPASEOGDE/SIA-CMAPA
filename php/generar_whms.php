<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

require_once __DIR__ . '/reportes_whms_utils.php';
$conn = db_conn_or_die();

$idCodigo = isset($_POST['idCodigo']) && $_POST['idCodigo'] !== '' ? (int)$_POST['idCodigo'] : null;
$mes      = $_POST['mes']  ?? date('m');
$anio     = $_POST['anio'] ?? date('Y');

$rows = fetch_movimientos_almacen($conn, $mes, $anio, $idCodigo);

// Notificación (solo al entrar a previsualización)
$usuario = htmlspecialchars($_SESSION['usuario'] ?? 'usuario');
$desc = "Reporte de MOVIMIENTOS generado por {$usuario} para " . ($idCodigo ? "producto $idCodigo" : "TODOS") . " en $mes/$anio";
insert_notification($conn, $desc, $idCodigo, null, 1); // idRol=1 (Admin)

list($start, $endNext) = range_from_month_year($mes, $anio);
$periodoTxt = date('Y-m-d', strtotime($start)) . " a " . date('Y-m-d', strtotime($endNext . ' -1 day'));
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Previsualización Movimientos Almacén</title>
  <link rel="stylesheet" href="css/StyleRPWHMS.css">
  <style>
    table.report { width:100%; border-collapse: collapse; }
    table.report th, table.report td { border:1px solid #ddd; padding:6px; font-size: 14px; }
    table.report th { background:#f2f2f2; text-align:left; }
    .meta { margin:12px 0; font-size:14px; }
  </style>
</head>
<body>
  <h2>Movimientos de Almacén</h2>
  <div class="meta"><strong>Periodo:</strong> <?= htmlspecialchars($periodoTxt) ?> 
    &nbsp;|&nbsp; <strong>Producto:</strong> <?= $idCodigo ? (int)$idCodigo : 'Todos' ?>
  </div>

  <form method="post" action="exportar_whms_pdf.php" style="display:inline;">
    <input type="hidden" name="idCodigo" value="<?= $idCodigo ?>">
    <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
    <input type="hidden" name="anio" value="<?= htmlspecialchars($anio) ?>">
    <button type="submit">Descargar PDF</button>
  </form>
  <form method="post" action="exportar_whms_excel.php" style="display:inline;">
    <input type="hidden" name="idCodigo" value="<?= $idCodigo ?>">
    <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
    <input type="hidden" name="anio" value="<?= htmlspecialchars($anio) ?>">
    <button type="submit">Descargar XLSX</button>
  </form>

  <table class="report" style="margin-top:14px;">
    <thead>
      <tr>
        <th>Fecha</th>
        <th>Movimiento</th>
        <th>Código</th>
        <th>Descripción</th>
        <th>Cantidad</th>
        <th>idHerramienta</th>
        <th>Identificador Único</th>
        <th>Detalle</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="8" style="text-align:center;">Sin resultados para el periodo.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['fecha']) ?></td>
            <td><?= htmlspecialchars($r['tipoMovimiento']) ?></td>
            <td><?= htmlspecialchars($r['sku']) ?></td>
            <td><?= htmlspecialchars($r['descripcion']) ?></td>
            <td><?= (int)$r['cantidad'] ?></td>
            <td><?= htmlspecialchars($r['idHerramienta'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['identificadorUnico'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['detalle'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
