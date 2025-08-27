<?php
// php/report_inventory_xlsx.php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
    foreach ($r as $k => $v) {
        if ($v instanceof DateTime) $r[$k] = $v->format('Y-m-d H:i');
        elseif (is_null($v))        $r[$k] = '';
    }
    $rows[] = $r;
}
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

// Crea hoja
$ss = new Spreadsheet();
$sheet = $ss->getActiveSheet();
$sheet->setTitle('Inventario');

// Encabezados + datos
$rowNum = 1;
if (!empty($rows)) {
    $headers = array_keys($rows[0]);
    $colNum = 1;
    foreach ($headers as $h) {
        $sheet->setCellValueByColumnAndRow($colNum, $rowNum, $h);
        $colNum++;
    }
    // Negritas a encabezado
    $sheet->getStyleByColumnAndRow(1, $rowNum, count($headers), $rowNum)
          ->getFont()->setBold(true);

    // Datos
    foreach ($rows as $r) {
        $rowNum++;
        $colNum = 1;
        foreach ($r as $val) {
            $sheet->setCellValueByColumnAndRow($colNum, $rowNum, $val);
            $colNum++;
        }
    }

    // Auto ancho
    for ($c = 1; $c <= count($headers); $c++) {
        $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
} else {
    $sheet->setCellValue('A1', 'No hay datos.');
}

// Descarga
$filename = 'inventario_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
