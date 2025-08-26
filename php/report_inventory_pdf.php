<?php
// php/report_inventory_pdf.php
session_start();

// Verifica sesión
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Composer autoload
require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Conexión SQL Server
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
    "Database" => "db_sia",
    "Uid"      => "cmapADMIN",
    "PWD"      => "@siaADMN56*",
    "Encrypt"  => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    http_response_code(500);
    echo "Error de conexión a SQL Server.";
    exit();
}

/**
 * NOTA:
 * Usa un SELECT sencillo y seguro que exista.
 * Puedes ampliar columnas/campos a tu gusto.
 */
$sql = "SELECT P.codigo AS Codigo, P.descripcion AS Descripcion
          FROM Productos P
      ORDER BY P.codigo";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    sqlsrv_close($conn);
    http_response_code(500);
    echo "Error al consultar datos.";
    exit();
}

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Normaliza DateTime/NULL si hiciera falta
    foreach ($r as $k => $v) {
        if ($v instanceof DateTime) $r[$k] = $v->format('Y-m-d H:i');
        elseif (is_null($v))        $r[$k] = '';
    }
    $rows[] = $r;
}
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

// Construye HTML
$fecha = date('Y-m-d H:i');
$thead = '';
$tbody = '';

if (!empty($rows)) {
    // Encabezados tomando las llaves del primer registro
    $thead .= '<tr>';
    foreach (array_keys($rows[0]) as $col) {
        $thead .= '<th>'.htmlspecialchars($col).'</th>';
    }
    $thead .= '</tr>';

    // Cuerpo
    foreach ($rows as $row) {
        $tbody .= '<tr>';
        foreach ($row as $val) {
            $tbody .= '<td>'.htmlspecialchars((string)$val).'</td>';
        }
        $tbody .= '</tr>';
    }
} else {
    $thead = '<tr><th>Información</th></tr>';
    $tbody = '<tr><td>No hay datos.</td></tr>';
}

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body{ font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size:12px; }
  h1{ font-size:18px; margin:0 0 8px 0; }
  .meta{ font-size:11px; color:#555; margin-bottom:12px; }
  table{ width:100%; border-collapse:collapse; }
  th, td{ border:1px solid #ccc; padding:6px 8px; }
  th{ background:#f2f2f2; text-align:left; }
</style>
</head>
<body>
  <h1>Reporte de Inventario</h1>
  <div class="meta">Generado: {$fecha} &nbsp; | &nbsp; Usuario: {$_SESSION['usuario']}</div>
  <table>
    <thead>{$thead}</thead>
    <tbody>{$tbody}</tbody>
  </table>
</body>
</html>
HTML;

// Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();

// Descarga
$filename = 'inventario_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
