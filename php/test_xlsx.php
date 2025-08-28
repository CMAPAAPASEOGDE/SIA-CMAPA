<?php
// php/test_xlsx.php
error_reporting(E_ALL); ini_set('display_errors', 1);

// Autoload
require __DIR__ . '/../vendor/autoload.php';

$ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sh = $ss->getActiveSheet()->setTitle('Test');
$sh->setCellValue('A1','Hola'); 
$sh->setCellValue('B1','XLSX OK');

// Limpia buffers (evita "headers already sent")
while (ob_get_level()) { ob_end_clean(); }

$filename = 'test_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
$writer->save('php://output');
exit;
