<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

require_once __DIR__ . '/kardex_core.php';
require_once __DIR__ . '/vendor/autoload.php'; // dompdf/dompdf

use Dompdf\Dompdf;

$conn     = db_conn();
$idCodigo = $_POST['idCodigo'] ?? 'ALL';
$desde    = $_POST['desde']    ?? '';
$hasta    = $_POST['hasta']    ?? '';
if (!$desde || !$hasta) die("Faltan fechas.");

$productos = traerProductos($conn, $idCodigo);

$html = '<h1 style="font-family:DejaVu Sans,Arial">KARDEX DE PRODUCTOS</h1>';
$html .= '<div>Periodo: '.htmlspecialchars($desde).' a '.htmlspecialchars($hasta).'</div>';

$granTotal = 0.0;
foreach ($productos as $p) {
  [$rows, $tot] = procesarKardexPorProducto($conn, $p['idCodigo'], $desde, $hasta);
  $html .= '<div style="page-break-inside:avoid; margin-top:14px;">';
  $html .= render_kardex_html($conn, $p, $desde, $hasta, $rows, $tot);
  $html .= '</div>';
  $granTotal += floatval($tot['kardex_total']);
}

$html .= '<h3 style="text-align:right;font-family:DejaVu Sans,Arial">Total global del reporte: '.number_format($granTotal,2).'</h3>';

$dompdf = new Dompdf(["isRemoteEnabled" => true]);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

notificar_kardex($conn, $idCodigo, $desde, $hasta, $_SESSION['usuario'] ?? 'usuario');

$dompdf->stream("kardex_{$idCodigo}_{$desde}_{$hasta}.pdf", ["Attachment" => true]);
