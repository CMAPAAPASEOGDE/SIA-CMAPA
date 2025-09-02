<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

require_once __DIR__ . '/kardex_core.php';
require_once __DIR__ . '/vendor/autoload.php'; // phpoffice/phpspreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$conn     = db_conn();
$idCodigo = $_POST['idCodigo'] ?? 'ALL';
$desde    = $_POST['desde']    ?? '';
$hasta    = $_POST['hasta']    ?? '';
if (!$desde || !$hasta) die("Faltan fechas.");

$productos = traerProductos($conn, $idCodigo);

$spread = new Spreadsheet();
$first = true;
$granTotal = 0.0;

foreach ($productos as $p) {
  $sheet = $first ? $spread->getActiveSheet() : $spread->createSheet();
  $first = false;
  $sheet->setTitle(substr($p['idCodigo'], 0, 31));

  $sheet->setCellValue('A1', 'KARDEX DE PRODUCTO');
  $sheet->setCellValue('A2', $p['idCodigo'].' - '.$p['descripcion'].(empty($p['codigo'])?'':' ('.$p['codigo'].')'));
  $sheet->setCellValue('A3', 'Línea: '.($p['linea'] ?? '').' | Sublinea: '.($p['sublinea'] ?? ''));
  $sheet->setCellValue('A4', "Periodo: $desde a $hasta");

  $headers = ['Fecha','Movimiento','Ent. Cant','Ent. Costo U.','Sal. Cant','Sal. Costo U.','Saldo Cant','Saldo Costo U.'];
  $col = 'A'; foreach ($headers as $h) { $sheet->setCellValue($col.'6', $h); $col++; }

  [$rows, $tot] = procesarKardexPorProducto($conn, $p['idCodigo'], $desde, $hasta);
  $r = 7;
  foreach ($rows as $row) {
    $sheet->setCellValue("A{$r}", is_object($row['fecha']) ? $row['fecha']->format('Y-m-d H:i') : $row['fecha']);
    $sheet->setCellValue("B{$r}", $row['tipo']);
    $sheet->setCellValue("C{$r}", $row['entrada_cant']);
    $sheet->setCellValue("D{$r}", $row['entrada_costou']);
    $sheet->setCellValue("E{$r}", $row['salida_cant']);
    $sheet->setCellValue("F{$r}", $row['salida_costou']);
    $sheet->setCellValue("G{$r}", $row['saldo_cant']);
    $sheet->setCellValue("H{$r}", $row['saldo_costou']);
    $r++;
  }

  $sheet->setCellValue("A{$r}", 'Totales'); $sheet->mergeCells("A{$r}:B{$r}");
  $sheet->setCellValue("C{$r}", $tot['entradas_cant']);
  $sheet->setCellValue("D{$r}", $tot['entradas_importe']);
  $sheet->setCellValue("E{$r}", $tot['salidas_cant']);
  $sheet->setCellValue("F{$r}", $tot['salidas_importe']);
  $sheet->setCellValue("G{$r}", $tot['saldo_final_cant']);
  $sheet->setCellValue("H{$r}", $tot['saldo_final_costou']);
  $r++;
  $sheet->setCellValue("A{$r}", 'Costo total del Kardex (salidas valoradas): '.$tot['kardex_total']); $sheet->mergeCells("A{$r}:H{$r}");

  foreach (range('A','H') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);

  $granTotal += floatval($tot['kardex_total']);
}

// Hoja resumen
$resumen = $spread->createSheet();
$resumen->setTitle('Resumen');
$resumen->setCellValue('A1','Resumen de Kardex'); 
$resumen->setCellValue('A2','Periodo: '.$desde.' a '.$hasta);
$resumen->setCellValue('A4','Total global del reporte (salidas valoradas)');
$resumen->setCellValue('B4', $granTotal);
$resumen->getColumnDimension('A')->setAutoSize(true);
$resumen->getColumnDimension('B')->setAutoSize(true);

notificar_kardex($conn, $idCodigo, $desde, $hasta, $_SESSION['usuario'] ?? 'usuario');

$fname = "kardex_{$idCodigo}_{$desde}_{$hasta}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$fname\"");
$writer = new Xlsx($spread);
$writer->save('php://output');
