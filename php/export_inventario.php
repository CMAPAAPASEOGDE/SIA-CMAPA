<?php
// php/export_inventario.php
session_start();

error_log("Export inventario llamado: " . print_r($_GET, true));

// Si hay algún error, registralo
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_errors.log');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
  http_response_code(403);
  echo "No autorizado";
  exit();
}

// ----------- Filtros ----------
$format   = strtolower($_GET['format'] ?? 'pdf'); // pdf | xlsx
$codigo   = trim((string)($_GET['codigo']   ?? ''));
$nombre   = trim((string)($_GET['nombre']   ?? ''));
$linea    = trim((string)($_GET['linea']    ?? ''));
$sublinea = trim((string)($_GET['sublinea'] ?? ''));
$tipo     = trim((string)($_GET['tipo']     ?? ''));
$estado   = trim((string)($_GET['estado']   ?? ''));

$includePrecio = ((int)($_SESSION['rol'] ?? 0) !== 2); // rol 2 no ve precio

// ----------- DB -----------
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
  "Database" => "db_sia",
  "Uid"      => "cmapADMIN",
  "PWD"      => "@siaADMN56*",
  "Encrypt"  => true,
  "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) { http_response_code(500); die("Error de conexión"); }

// SQL (subconsulta para estado)
$sql = "
SELECT * FROM (
  SELECT
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
  INNER JOIN Inventario i ON p.idCodigo = i.idCodigo
) AS T
WHERE 1=1
";
$params = [];
if ($codigo   !== '') { $sql .= " AND T.codigo LIKE ?";        $params[] = "%$codigo%"; }
if ($nombre   !== '') { $sql .= " AND T.descripcion LIKE ?";   $params[] = "%$nombre%"; }
if ($linea    !== '') { $sql .= " AND T.linea = ?";            $params[] = $linea; }
if ($sublinea !== '') { $sql .= " AND T.sublinea = ?";         $params[] = $sublinea; }
if ($tipo     !== '') { $sql .= " AND T.tipo = ?";             $params[] = $tipo; }
if ($estado   !== '') { $sql .= " AND T.estado = ?";           $params[] = $estado; }
$sql .= " ORDER BY T.codigo ASC";

$stmt = sqlsrv_query($conn, $sql, $params);
if (!$stmt) { sqlsrv_close($conn); http_response_code(500); die("Error en consulta"); }

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $rows[] = $r; }
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

// ----------- Datos comunes -----------
$fecha   = (new DateTime('now'))->format('Y-m-d H:i');
$usuario = (string)($_SESSION['usuario'] ?? '');
$headers = ['Código','Descripción','Línea','Sublínea','Cantidad','Unidad'];
if ($includePrecio) { $headers[] = 'Precio'; }
$headers = array_merge($headers, ['Pto. Reorden','Stock Máx.','Tipo','Estado']);

// Autoload (PDF/XLSX)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) { http_response_code(500); die('Falta autoload de Composer.'); }
require $autoload;

// ----------- PDF -----------
if ($format === 'pdf') {
  ob_start();
  ?>
  <html>
  <head>
    <meta charset="UTF-8">
    <style>
      body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; }
      h1 { font-size: 16px; margin: 0 0 8px 0; }
      .meta { font-size: 10px; color: #555; margin-bottom: 10px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #ccc; padding: 6px 8px; }
      th { background: #f2f2f2; }
      .right { text-align: right; }
    </style>
  </head>
  <body>
    <h1>Inventario del almacén</h1>
    <div class="meta">
      Generado: <?= htmlspecialchars($fecha) ?> &nbsp;|&nbsp; Usuario: <?= htmlspecialchars($usuario) ?>
    </div>
    <table>
      <thead>
        <tr>
          <?php foreach ($headers as $h): ?><th><?= htmlspecialchars($h) ?></th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="<?= count($headers) ?>">Sin resultados</td></tr>
        <?php else: foreach ($rows as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['codigo']) ?></td>
            <td><?= htmlspecialchars($row['descripcion']) ?></td>
            <td><?= htmlspecialchars($row['linea']) ?></td>
            <td><?= htmlspecialchars($row['sublinea']) ?></td>
            <td class="right"><?= (int)$row['cantidad'] ?></td>
            <td><?= htmlspecialchars($row['unidad']) ?></td>
            <?php if ($includePrecio): ?><td class="right"><?= number_format((float)$row['precio'], 2) ?></td><?php endif; ?>
            <td class="right"><?= (int)$row['puntoReorden'] ?></td>
            <td class="right"><?= (int)$row['stockMaximo'] ?></td>
            <td><?= htmlspecialchars($row['tipo']) ?></td>
            <td><?= htmlspecialchars($row['estado']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </body>
  </html>
  <?php
  $html = ob_get_clean();

  $opt = new \Dompdf\Options(); $opt->set('isRemoteEnabled', true);
  $dompdf = new \Dompdf\Dompdf($opt);
  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4', 'landscape');
  $dompdf->render();
  $dompdf->stream('inventario_'.date('Ymd_His').'.pdf', ['Attachment' => true]);
  exit;
}

// ----------- XLSX -----------
if ($format === 'xlsx') {
  try {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet()->setTitle('Inventario');

    // encabezados
    $c = 1;
    foreach ($headers as $h) {
      $sheet->setCellValueByColumnAndRow($c, 1, $h);
      $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
      $c++;
    }
    // filas
    $r = 2;
    foreach ($rows as $row) {
      $c = 1;
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['codigo']);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['descripcion']);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['linea']);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['sublinea']);
      $sheet->setCellValueByColumnAndRow($c++, $r, (int)$row['cantidad']);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['unidad']);
      if ($includePrecio) $sheet->setCellValueByColumnAndRow($c++, $r, (float)$row['precio']);
      $sheet->setCellValueByColumnAndRow($c++, $r, (int)$row['puntoReorden']);
      $sheet->setCellValueByColumnAndRow($c++, $r, (int)$row['stockMaximo']);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['tipo']);
      $sheet->setCellValueByColumnAndRow($c++, $r, $row['estado']);
      $r++;
    }
    $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);

    // limpia buffers antes de headers
    while (ob_get_level()) { ob_end_clean(); }

    $filename = 'inventario_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

  } catch (\Throwable $e) {
    if (!headers_sent()) {
      header('Content-Type: text/plain; charset=utf-8', true, 500);
    }
    echo "Error generando Excel: ".$e->getMessage()."\n".$e->getTraceAsString();
    exit;
  }
}

// formato inválido
http_response_code(400);
echo "Formato inválido.";
