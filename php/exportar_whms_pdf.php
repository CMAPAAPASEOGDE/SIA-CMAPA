<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../index.php"); // << clave: subir un nivel
    exit();
}

require_once __DIR__ . '/reportes_whms_utils.php';

// autoload de composer: primero intenta ../vendor y si no, ./vendor
$autoloadRoot = __DIR__ . '/../vendor/autoload.php';
$autoloadHere = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadRoot)) {
    require_once $autoloadRoot;
} elseif (file_exists($autoloadHere)) {
    require_once $autoloadHere;
} else {
    die('No se encontró vendor/autoload.php');
}

use Dompdf\Dompdf;

$conn = db_conn_or_die();
$idCodigo = isset($_POST['idCodigo']) && $_POST['idCodigo'] !== '' ? (int)$_POST['idCodigo'] : null;
$mes      = $_POST['mes']  ?? date('m');
$anio     = $_POST['anio'] ?? date('Y');

$rows = fetch_movimientos_almacen($conn, $mes, $anio, $idCodigo);
list($start, $endNext) = range_from_month_year($mes, $anio);
$periodoTxt = date('Y-m-d', strtotime($start)) . " a " . date('Y-m-d', strtotime("$endNext -1 day"));

$html = '<html><head><meta charset="UTF-8"><style>
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; }
  h3 { margin: 0 0 6px 0; }
  .meta { margin-bottom:8px; }
  table { width:100%; border-collapse: collapse; }
  th, td { border:1px solid #ccc; padding:4px; }
  th { background:#eee; }
</style></head><body>';

$html .= '<h3>Movimientos de Almacén</h3>';
$html .= '<div class="meta"><strong>Periodo:</strong> '.htmlspecialchars($periodoTxt)
      .' &nbsp;|&nbsp; <strong>Producto:</strong> '.($idCodigo ? (int)$idCodigo : 'Todos').'</div>';

$html .= '<table><thead><tr>
            <th>Fecha</th><th>Movimiento</th><th>Código</th><th>Descripción</th>
            <th>Cantidad</th><th>idHerramienta</th><th>Identificador Único</th><th>Detalle</th>
          </tr></thead><tbody>';

if (empty($rows)) {
    $html .= '<tr><td colspan="8" style="text-align:center;">Sin resultados</td></tr>';
} else {
    foreach ($rows as $r) {
        $html .= '<tr>'.
            '<td>'.htmlspecialchars($r['fecha']).'</td>'.
            '<td>'.htmlspecialchars($r['tipoMovimiento']).'</td>'.
            '<td>'.htmlspecialchars($r['sku']).'</td>'.
            '<td>'.htmlspecialchars($r['descripcion']).'</td>'.
            '<td>'.(int)$r['cantidad'].'</td>'.
            '<td>'.htmlspecialchars($r['idHerramienta'] ?? '').'</td>'.
            '<td>'.htmlspecialchars($r['identificadorUnico'] ?? '').'</td>'.
            '<td>'.htmlspecialchars($r['detalle'] ?? '').'</td>'.
        '</tr>';
    }
}
$html .= '</tbody></table></body></html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = 'Movimientos_Almacen_' . $anio . '-' . $mes . ($idCodigo ? ('_prod'.$idCodigo) : '') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
