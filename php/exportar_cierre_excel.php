<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
require_once __DIR__.'/month_close_utils.php';

$autoloadRoot = __DIR__.'/../vendor/autoload.php';
$autoloadHere = __DIR__.'/vendor/autoload.php';
if (file_exists($autoloadRoot)) require_once $autoloadRoot;
elseif (file_exists($autoloadHere)) require_once $autoloadHere;
else die('No se encontró vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$conn = db_conn_or_die();
$idCierre = isset($_POST['idCierre']) ? (int)$_POST['idCierre'] : 0;
if ($idCierre<=0) die('Falta idCierre');

$cierre = get_cierre($conn,$idCierre);
if(!$cierre) die('Cierre no existe');

$fi = ($cierre['fechaInicio'] instanceof DateTime) ? $cierre['fechaInicio']->format('Y-m-d') : substr((string)$cierre['fechaInicio'],0,10);
$ff = ($cierre['fechaFin']    instanceof DateTime) ? $cierre['fechaFin']->format('Y-m-d')    : substr((string)$cierre['fechaFin'],0,10);

$movs  = fetch_movs_con_costos($conn,$fi,$ff);
$cajas = get_snapshot_cierre($conn,$idCierre);

$ss = new Spreadsheet();

/* Hoja 1: Movimientos */
$sh = $ss->getActiveSheet();
$sh->setTitle('Movimientos');
$sh->fromArray(['Fecha','Tipo','Código','Descripción','Cantidad','$ Unitario','$ Total','Detalle'], null, 'A1');
$r=2;
foreach($movs as $m){
  $sh->setCellValue("A$r",$m['fecha']);
  $sh->setCellValue("B$r",$m['tipo']);
  $sh->setCellValue("C$r",$m['sku']);
  $sh->setCellValue("D$r",$m['descripcion']);
  $sh->setCellValue("E$r",(int)$m['cantidad']);
  $sh->setCellValue("F$r",(float)$m['precioUnitario']);
  $sh->setCellValue("G$r",(float)$m['total']);
  $sh->setCellValue("H$r",$m['info'] ?? '');
  $r++;
}
foreach(range('A','H') as $c){ $sh->getColumnDimension($c)->setAutoSize(true); }
$sh->getStyle('A1:H1')->getFont()->setBold(true);

/* Hoja 2: Cajas */
$sh2 = $ss->createSheet();
$sh2->setTitle('Cajas');
$sh2->fromArray(['Caja','Código','Descripción','Cantidad'], null, 'A1');
$r=2;
foreach($cajas as $c){
  $sh2->setCellValue("A$r",$c['numeroCaja']);
  $sh2->setCellValue("B$r",$c['sku']);
  $sh2->setCellValue("C$r",$c['descripcion']);
  $sh2->setCellValue("D$r",(int)$c['cantidad']);
  $r++;
}
foreach(range('A','D') as $c){ $sh2->getColumnDimension($c)->setAutoSize(true); }
$sh2->getStyle('A1:D1')->getFont()->setBold(true);

$fn='CierreMes_'.$cierre['etiqueta'].'_'.$fi.'_a_'.$ff.'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fn.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($ss); $writer->save('php://output'); exit;
