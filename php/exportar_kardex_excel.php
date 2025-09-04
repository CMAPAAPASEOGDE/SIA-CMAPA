<?php
session_start();

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

require_once __DIR__ . '/kardex_core.php';

// Try multiple autoload paths
$autoloadPaths = [
    dirname(__DIR__) . '/vendor/autoload.php',  // parent directory
    __DIR__ . '/../vendor/autoload.php',        // same as above
    __DIR__ . '/vendor/autoload.php',           // current directory
    'vendor/autoload.php'                       // relative path
];

$autoload = null;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        $autoload = $path;
        error_log("Found autoload at: " . $path);
        break;
    }
}

if (!$autoload) {
    error_log("Autoload not found. Checked paths: " . implode(', ', $autoloadPaths));
    die('Error: Composer autoload not found. Please run: composer install');
}

require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

try {
    $conn     = db_conn();
    $idCodigo = $_POST['idCodigo'] ?? 'ALL';
    $desde    = $_POST['desde']    ?? '';
    $hasta    = $_POST['hasta']    ?? '';
    
    if (!$desde || !$hasta) {
        die("Error: Faltan fechas requeridas.");
    }
    
    error_log("Generating Kardex XLSX for: $idCodigo, $desde to $hasta");
    
    $productos = traerProductos($conn, $idCodigo);
    
    if (empty($productos)) {
        die("Error: No se encontraron productos.");
    }

    // Clear any existing output
    while (ob_get_level()) {
        ob_end_clean();
    }

    $spread = new Spreadsheet();
    $first = true;
    $granTotal = 0.0;

    foreach ($productos as $p) {
        $sheet = $first ? $spread->getActiveSheet() : $spread->createSheet();
        $first = false;
        
        // Limit sheet title to 31 characters (Excel limitation)
        $sheetTitle = substr($p['idCodigo'], 0, 31);
        $sheet->setTitle($sheetTitle);

        // Header information
        $sheet->setCellValue('A1', 'KARDEX DE PRODUCTO');
        $sheet->setCellValue('A2', $p['idCodigo'].' - '.$p['descripcion'].(empty($p['codigo'])?'':' ('.$p['codigo'].')'));
        $sheet->setCellValue('A3', 'Línea: '.($p['linea'] ?? '').' | Sublinea: '.($p['sublinea'] ?? ''));
        $sheet->setCellValue('A4', "Periodo: $desde a $hasta");

        // Table headers
        $headers = ['Fecha','Movimiento','Ent. Cant','Ent. Costo U.','Sal. Cant','Sal. Costo U.','Saldo Cant','Saldo Costo U.'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        
        for ($i = 0; $i < count($headers); $i++) {
            $sheet->setCellValue($columns[$i] . '6', $headers[$i]);
        }

        // Get and process kardex data
        [$rows, $tot] = procesarKardexPorProducto($conn, $p['idCodigo'], $desde, $hasta);
        
        // Data rows
        $rowNum = 7;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$rowNum}", is_object($row['fecha']) ? $row['fecha']->format('Y-m-d H:i') : $row['fecha']);
            $sheet->setCellValue("B{$rowNum}", $row['tipo']);
            $sheet->setCellValue("C{$rowNum}", $row['entrada_cant']);
            $sheet->setCellValue("D{$rowNum}", $row['entrada_costou']);
            $sheet->setCellValue("E{$rowNum}", $row['salida_cant']);
            $sheet->setCellValue("F{$rowNum}", $row['salida_costou']);
            $sheet->setCellValue("G{$rowNum}", $row['saldo_cant']);
            $sheet->setCellValue("H{$rowNum}", $row['saldo_costou']);
            $rowNum++;
        }

        // Totals row
        $sheet->setCellValue("A{$rowNum}", 'Totales');
        $sheet->mergeCells("A{$rowNum}:B{$rowNum}");
        $sheet->setCellValue("C{$rowNum}", $tot['entradas_cant']);
        $sheet->setCellValue("D{$rowNum}", $tot['entradas_importe']);
        $sheet->setCellValue("E{$rowNum}", $tot['salidas_cant']);
        $sheet->setCellValue("F{$rowNum}", $tot['salidas_importe']);
        $sheet->setCellValue("G{$rowNum}", $tot['saldo_final_cant']);
        $sheet->setCellValue("H{$rowNum}", $tot['saldo_final_costou']);
        $rowNum++;
        
        // Total cost row
        $sheet->setCellValue("A{$rowNum}", 'Costo total del Kardex (salidas valoradas): ' . number_format($tot['kardex_total'], 2));
        $sheet->mergeCells("A{$rowNum}:H{$rowNum}");

        // Auto-size columns
        foreach ($columns as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $granTotal += floatval($tot['kardex_total']);
    }

    // Summary sheet
    $resumen = $spread->createSheet();
    $resumen->setTitle('Resumen');
    $resumen->setCellValue('A1', 'Resumen de Kardex');
    $resumen->setCellValue('A2', 'Periodo: ' . $desde . ' a ' . $hasta);
    $resumen->setCellValue('A4', 'Total global del reporte (salidas valoradas)');
    $resumen->setCellValue('B4', number_format($granTotal, 2));
    $resumen->getColumnDimension('A')->setAutoSize(true);
    $resumen->getColumnDimension('B')->setAutoSize(true);

    // Set the first sheet as active
    $spread->setActiveSheetIndex(0);

    // Optional: Add notification logging (instead of missing function)
    error_log("Kardex XLSX generated by user: " . ($_SESSION['usuario'] ?? 'unknown'));

    // Generate filename
    $filename = "kardex_{$idCodigo}_{$desde}_{$hasta}.xlsx";
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1'); // For IE
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // Always modified
    header('Cache-Control: cache, must-revalidate'); // For IE
    header('Pragma: public'); // For IE

    $writer = new Xlsx($spread);
    $writer->save('php://output');

    sqlsrv_close($conn);
    exit();

} catch (Exception $e) {
    error_log("Kardex XLSX Error: " . $e->getMessage() . " - " . $e->getTraceAsString());
    
    // Clear any output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Error generando XLSX del Kardex: " . $e->getMessage();
    exit();
}
?>