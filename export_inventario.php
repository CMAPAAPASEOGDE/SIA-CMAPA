<?php
// php/export_inventario.php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
  http_response_code(403);
  echo "No autorizado";
  exit();
}

// ----------- Leer filtros ----------
$format   = strtolower($_GET['format'] ?? 'pdf'); // pdf | xlsx
$codigo   = trim((string)($_GET['codigo']   ?? ''));
$nombre   = trim((string)($_GET['nombre']   ?? ''));
$linea    = trim((string)($_GET['linea']    ?? ''));
$sublinea = trim((string)($_GET['sublinea'] ?? ''));
$tipo     = trim((string)($_GET['tipo']     ?? ''));
$estado   = trim((string)($_GET['estado']   ?? ''));

$includePrecio = ((int)($_SESSION['rol'] ?? 0) !== 2); // Almacenista (2) no ve precio

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
if (!$conn) { die("Error de conexión"); }

// Query como subconsulta para poder filtrar por 'estado'
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
if (!$stmt) { die("Error en consulta"); }

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
  $rows[] = $r;
}
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

// ------------- Salidas -------------
$fecha = (new DateTime('now'))->format('Y-m-d H:i');
$usuario = (string)($_SESSION['usuario'] ?? '');

// Encabezados comunes
$headers = [
  'Código','Descripción','Línea','Sublínea','Cantidad','Unidad'
];
if ($includePrecio) { $headers[] = 'Precio'; }
$headers = array_merge($headers, ['Pto. Reorden','Stock Máx.','Tipo','Estado']);

// Carga Composer (lo usamos en PDF/XLSX)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
  die('Falta autoload de Composer. Ejecuta: composer install');
}
require $autoload;

// ------------------ PDF ------------------
if ($format === 'pdf') {

  // Armar HTML
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
          <?php foreach ($headers as $h): ?>
            <th><?= htmlspecialchars($h) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="<?= count($headers) ?>">Sin resultados</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['codigo']) ?></td>
              <td><?= htmlspecialchars($row['descripcion']) ?></td>
              <td><?= htmlspecialchars($row['linea']) ?></td>
              <td><?= htmlspecialchars($row['sublinea']) ?></td>
              <td class="right"><?= (int)$row['cantidad'] ?></td>
              <td><?= htmlspecialchars($row['unidad']) ?></td>
              <?php if ($includePrecio): ?>
                <td class="right"><?= number_format((float)$row['precio'], 2) ?></td>
              <?php endif; ?>
              <td class="right"><?= (int)$row['puntoReorden'] ?></td>
              <td class="right"><?= (int)$row['stockMaximo'] ?></td>
              <td><?= htmlspecialchars($row['tipo']) ?></td>
              <td><?= htmlspecialchars($row['estado']) ?></td>
            </tr>
          <?php endforeach; ?> 
        <?php endif; ?>
      </tbody>
    </table>
  </body>
  </html>
  <?php
  $html = ob_get_clean();

  // Dompdf (FQCN)
  $opt = new \Dompdf\Options();
  $opt->set('isRemoteEnabled', true);
  $dompdf = new \Dompdf\Dompdf($opt);
  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4', 'landscape');
  $dompdf->render();

  $filename = 'inventario_' . date('Ymd_His') . '.pdf';
  $dompdf->stream($filename, ['Attachment' => true]);
  exit();
}

// ------------------ XLSX ------------------
if ($format === 'xlsx') {

  $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
  $sheet = $spreadsheet->getActiveSheet();
  $sheet->setTitle('Inventario');

  // Encabezados
  $col = 1;
  foreach ($headers as $h) {
    $sheet->setCellValueByColumnAndRow($col, 1, $h);
    $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
    $col++;
  }
  // Filas
  $rowNum = 2;
  foreach ($rows as $row) {
    $col = 1;
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['codigo']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['descripcion']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['linea']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['sublinea']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, (int)$row['cantidad']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['unidad']);
    if ($includePrecio) {
      $sheet->setCellValueByColumnAndRow($col++, $rowNum, (float)$row['precio']);
    }
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, (int)$row['puntoReorden']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, (int)$row['stockMaximo']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['tipo']);
    $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row['estado']);
    $rowNum++;
  }

  // Primera fila en bold
  $sheet->getStyle('A1:'. $sheet->getHighestColumn() .'1')->getFont()->setBold(true);

  // Descargar
  $filename = 'inventario_' . date('Ymd_His') . '.xlsx';
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
  header('Cache-Control: max-age=0');

  $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
  if (ob_get_length()) { ob_end_clean(); }
  $writer->save('php://output');
  exit();
}

// Formato no soportado
http_response_code(400);
echo "Formato inválido.";
