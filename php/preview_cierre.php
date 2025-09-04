<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
require_once __DIR__.'/month_close_utils.php';
$conn = db_conn_or_die();
$period = compute_close_period();
$movs  = fetch_movs_con_costos($conn,$period['fechaInicio'],$period['fechaFin']);
$cajas = get_cajas_snapshot_now($conn);

$totE=0;$totS=0; foreach($movs as $m){ if(strpos($m['tipo'],'ENTRADA')===0) $totE+=(float)$m['total']; else $totS+=(float)$m['total']; }
?>
<!doctype html><html><head><meta charset="utf-8"><title>Previsualización</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;margin:16px}
h2{margin:0 0 8px} table{width:100%;border-collapse:collapse;margin-top:8px}
th,td{border:1px solid #ddd;padding:6px;font-size:13px} th{background:#f2f2f2;text-align:left}
.section{margin-top:18px} .tot{font-weight:bold}
</style></head><body>
<h2>Cierre de Mes — <?= htmlspecialchars($period['etiqueta']) ?></h2>
<div><strong>Periodo:</strong> <?= htmlspecialchars($period['fechaInicio'].' a '.$period['fechaFin']) ?></div>

<div class="section">
  <h3>Movimientos con costo</h3>
  <table><thead><tr>
    <th>Fecha</th><th>Tipo</th><th>Código</th><th>Descripción</th><th>Cant.</th><th>$ Unit.</th><th>$ Total</th><th>Detalle</th>
  </tr></thead><tbody>
  <?php if(!$movs): ?><tr><td colspan="8" style="text-align:center">Sin movimientos</td></tr>
  <?php else: foreach($movs as $m): ?>
    <tr>
      <td><?= htmlspecialchars($m['fecha']) ?></td>
      <td><?= htmlspecialchars($m['tipo']) ?></td>
      <td><?= htmlspecialchars($m['sku']) ?></td>
      <td><?= htmlspecialchars($m['descripcion']) ?></td>
      <td><?= (int)$m['cantidad'] ?></td>
      <td><?= number_format((float)$m['precioUnitario'],2) ?></td>
      <td><?= number_format((float)$m['total'],2) ?></td>
      <td><?= htmlspecialchars($m['info'] ?? '') ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody></table>
  <p class="tot">Total Entradas: $<?= number_format($totE,2) ?> &nbsp; | &nbsp; Total Salidas: $<?= number_format($totS,2) ?></p>
</div>

<div class="section">
  <h3>Cajas (snapshot actual)</h3>
  <table><thead><tr><th>Caja</th><th>Código</th><th>Descripción</th><th>Cantidad</th></tr></thead><tbody>
  <?php if(!$cajas): ?><tr><td colspan="4" style="text-align:center">Sin datos de cajas</td></tr>
  <?php else: foreach($cajas as $c): ?>
    <tr>
      <td><?= htmlspecialchars($c['numeroCaja']) ?></td>
      <td><?= htmlspecialchars($c['sku']) ?></td>
      <td><?= htmlspecialchars($c['descripcion']) ?></td>
      <td><?= (int)$c['cantidad'] ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody></table>
</div>
</body></html>
