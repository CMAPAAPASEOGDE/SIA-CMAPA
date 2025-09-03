<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../index.php"); // << clave
    exit();
}

require_once __DIR__ . '/reportes_whms_utils.php';

// autoload de composer
$autoloadRoot = __DIR__ . '/../vendor/autoload.php';
$autoloadHere = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadRoot)) {
    require_once $autoloadRoot;
} elseif (file_exists($autoloadHere)) {
    require_once $autoloadHere;
} else {
    die('No se encontró vendor/autoload.php');
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$conn = db_conn_or_die();
$idCodigo = isset($_POST['idCodigo']) && $_POST['idCodigo'] !== '' ? (int)$_POST['idCodigo'] : null;
$mes      = $_POST['mes']  ?? date('m');
$anio     = $_POST['anio'] ?? date('Y');

$rows = fetch_movimientos_almacen($conn, $mes, $anio, $idCodigo);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Movimientos');

$headers = ['Fecha','Movimiento','Código','Descripción','Cantidad','idHerramienta','Identificador Único','Detalle'];
$sheet->fromArray($headers, null, 'A1');

$r = 2;
foreach ($rows as $row) {
    $sheet->setCellValue("A{$r}", $row['fecha']);
    $sheet->setCellValue("B{$r}", $row['tipoMovimiento']);
    $sheet->setCellValue("C{$r}", $row['sku']);
    $sheet->setCellValue("D{$r}", $row['descripcion']);
    $sheet->setCellValue("E{$r}", (int)$row['cantidad']);
    $sheet->setCellValue("F{$r}", $row['idHerramienta'] ?? '');
    $sheet->setCellValue("G{$r}", $row['identificadorUnico'] ?? '');
    $sheet->setCellValue("H{$r}", $row['detalle'] ?? '');
    $r++;
}

foreach (range('A','H') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
$sheet->getStyle('A1:H1')->getFont()->setBold(true);

$filename = 'Movimientos_Almacen_' . $anio . '-' . $mes . ($idCodigo ? ('_prod'.$idCodigo) : '') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
 