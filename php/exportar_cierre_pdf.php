<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
require_once __DIR__.'/month_close_utils.php';

require_once __DIR__ . '/log_utils.php';
$conn = db_conn_or_die(); logs_boot($conn);
log_event($conn, (int)$_SESSION['user_id'], 'REP_WHMS_PDF',
          'WHMS PDF mes='.$mes.' anio='.$anio.' idCodigo='.($idCodigo ?? 'Todos'),
          'REPORTES', 1);

$autoloadRoot = __DIR__.'/../vendor/autoload.php';
$autoloadHere = __DIR__.'/vendor/autoload.php';
if (file_exists($autoloadRoot)) require_once $autoloadRoot;
elseif (file_exists($autoloadHere)) require_once $autoloadHere;
else die('No se encontró vendor/autoload.php');

use Dompdf\Dompdf;

$conn = db_conn_or_die();
$idCierre = isset($_POST['idCierre']) ? (int)$_POST['idCierre'] : 0;
if ($idCierre<=0) die('Falta idCierre');

$cierre = get_cierre($conn,$idCierre);
if(!$cierre) die('Cierre no existe');

$fi = ($cierre['fechaInicio'] instanceof DateTime) ? $cierre['fechaInicio']->format('Y-m-d') : substr((string)$cierre['fechaInicio'],0,10);
$ff = ($cierre['fechaFin']    instanceof DateTime) ? $cierre['fechaFin']->format('Y-m-d')    : substr((string)$cierre['fechaFin'],0,10);

$movs  = fetch_movs_con_costos($conn,$fi,$ff);
$cajas = get_snapshot_cierre($conn,$idCierre);

$totE=0;$totS=0; foreach($movs as $m){ if(strpos($m['tipo'],'ENTRADA')===0) $totE+=(float)$m['total']; else $totS+=(float)$m['total']; }

$html = '<html><head><meta charset="UTF-8"><style>
body{font-family:DejaVu Sans,Arial,Helvetica,sans-serif;font-size:12px}
h2{margin:0 0 6px} table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ccc;padding:4px} th{background:#eee} .mt{margin-top:10px}
</style></head><body>';

$html .= '<h2>CIERRE DE MES — '.htmlspecialchars($cierre['etiqueta']).'</h2>';
$html .= '<div><strong>Periodo:</strong> '.htmlspecialchars($fi.' a '.$ff).'</div>';

$html .= '<h3 class="mt">Movimientos con costo</h3>
<table><thead><tr>
<th>Fecha</th><th>Tipo</th><th>Código</th><th>Descripción</th><th>Cant.</th><th>$ Unit.</th><th>$ Total</th><th>Detalle</th>
</tr></thead><tbody>';
if(!$movs){ $html.='<tr><td colspan="8" style="text-align:center">Sin movimientos</td></tr>'; }
else{
  foreach($movs as $m){
    $html.='<tr>'.
      '<td>'.htmlspecialchars($m['fecha']).'</td>'.
      '<td>'.htmlspecialchars($m['tipo']).'</td>'.
      '<td>'.htmlspecialchars($m['sku']).'</td>'.
      '<td>'.htmlspecialchars($m['descripcion']).'</td>'.
      '<td>'.(int)$m['cantidad'].'</td>'.
      '<td>'.number_format((float)$m['precioUnitario'],2).'</td>'.
      '<td>'.number_format((float)$m['total'],2).'</td>'.
      '<td>'.htmlspecialchars($m['info'] ?? '').'</td>'.
    '</tr>';
  }
}
$html .= '</tbody></table>';
$html .= '<p><strong>Total Entradas:</strong> $'.number_format($totE,2).' &nbsp; | &nbsp; <strong>Total Salidas:</strong> $'.number_format($totS,2).'</p>';

$html .= '<h3 class="mt">Cajas (snapshot al cierre)</h3>
<table><thead><tr><th>Caja</th><th>Código</th><th>Descripción</th><th>Cantidad</th></tr></thead><tbody>';
if(!$cajas){ $html.='<tr><td colspan="4" style="text-align:center">Sin datos</td></tr>'; }
else{
  foreach($cajas as $c){
    $html.='<tr>'.
      '<td>'.htmlspecialchars($c['numeroCaja']).'</td>'.
      '<td>'.htmlspecialchars($c['sku']).'</td>'.
      '<td>'.htmlspecialchars($c['descripcion']).'</td>'.
      '<td>'.(int)$c['cantidad'].'</td>'.
    '</tr>';
  }
}
$html .= '</tbody></table></body></html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html,'UTF-8');
$dompdf->setPaper('A4','landscape');
$dompdf->render();
$fn='CierreMes_'.$cierre['etiqueta'].'_'.$fi.'_a_'.$ff.'.pdf';
$dompdf->stream($fn,['Attachment'=>true]);
exit;
