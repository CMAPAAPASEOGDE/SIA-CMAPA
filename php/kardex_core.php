<?php
// kardex_core.php

function db_conn() {
  $serverName = "sqlserver-sia.database.windows.net";
  $connectionOptions = [
    "Database" => "db_sia",
    "Uid"      => "cmapADMIN",
    "PWD"      => "@siaADMN56*",
    "Encrypt"  => true,
    "TrustServerCertificate" => false
  ];
  $conn = sqlsrv_connect($serverName, $connectionOptions);
  if ($conn === false) { die(print_r(sqlsrv_errors(), true)); }
  return $conn;
}

function traerProductos($conn, $idCodigo) {
  if ($idCodigo === 'ALL') {
    $rows = [];
    $q = "SELECT idCodigo, descripcion, linea, sublinea, codigo FROM dbo.Productos ORDER BY descripcion";
    $st = sqlsrv_query($conn, $q);
    while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $rows[] = $r;
    sqlsrv_free_stmt($st);
    return $rows;
  } else {
    $q = "SELECT idCodigo, descripcion, linea, sublinea, codigo FROM dbo.Productos WHERE idCodigo = ?";
    $st = sqlsrv_query($conn, $q, [$idCodigo]);
    $r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($st);
    return $r ? [$r] : [];
  }
}

// Devuelve el precio unitario de Productos (costo base)
function costoProducto($conn, $idCodigo) {
  $q = "SELECT TOP 1 CAST(precio AS FLOAT) AS precio FROM dbo.Productos WHERE idCodigo = ?";
  $st = sqlsrv_query($conn, $q, [$idCodigo]);
  $precio = 0.0;
  if ($st && ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC))) $precio = floatval($r['precio'] ?? 0);
  sqlsrv_free_stmt($st);
  return $precio ?: 0.0;
}

// Saldo inicial (antes del período): entradas - salidas
function saldoInicial($conn, $idCodigo, $desde) {
  $qe = "SELECT SUM(CAST(cantidad AS FLOAT)) AS cant
         FROM dbo.Entradas
         WHERE fecha < ? AND idCodigo = ?";
  $se = sqlsrv_query($conn, $qe, [$desde, $idCodigo]);
  $e  = sqlsrv_fetch_array($se, SQLSRV_FETCH_ASSOC) ?: ['cant'=>0]; sqlsrv_free_stmt($se);

  $qs = "SELECT SUM(CAST(x.cantidad AS FLOAT)) AS cant FROM (
            SELECT cantidad FROM dbo.Salidas WHERE fechaSalida < ? AND idCodigo = ?
            UNION ALL
            SELECT cantidad FROM dbo.SalidaSinorden WHERE fecha < ? AND idCodigo = ?
         ) x";
  $ss = sqlsrv_query($conn, $qs, [$desde, $idCodigo, $desde, $idCodigo]);
  $s  = sqlsrv_fetch_array($ss, SQLSRV_FETCH_ASSOC) ?: ['cant'=>0]; sqlsrv_free_stmt($ss);

  $qtyIni = max(0.0, floatval($e['cant'] ?? 0) - floatval($s['cant'] ?? 0));
  $avg    = costoProducto($conn, $idCodigo); // costo base por falta de costo en Entradas
  return ['qty' => $qtyIni, 'avgCost' => $avg];
}

// Movimientos unificados del período
function movimientosPeriodo($conn, $idCodigo, $desde, $hasta) {
  $filter = ($idCodigo === 'ALL') ? "" : " AND idCodigo = ? ";
  $sql = "
    SELECT fecha AS fecha, 'E' AS tipo, idCodigo, CAST(cantidad AS FLOAT) AS cantidad
    FROM dbo.Entradas
    WHERE fecha >= ? AND fecha <= ? $filter

    UNION ALL

    SELECT fechaSalida AS fecha, 'S' AS tipo, idCodigo, CAST(cantidad AS FLOAT) AS cantidad
    FROM dbo.Salidas
    WHERE fechaSalida >= ? AND fechaSalida <= ? $filter

    UNION ALL

    SELECT fecha AS fecha, 'S' AS tipo, idCodigo, CAST(cantidad AS FLOAT) AS cantidad
    FROM dbo.SalidaSinorden
    WHERE fecha >= ? AND fecha <= ? $filter

    ORDER BY fecha ASC, tipo ASC
  ";

  $p = ($idCodigo === 'ALL')
      ? [$desde,$hasta, $desde,$hasta, $desde,$hasta]
      : [$desde,$hasta,$idCodigo, $desde,$hasta,$idCodigo, $desde,$hasta,$idCodigo];

  $st = sqlsrv_query($conn, $sql, $p);
  if ($st === false) { die(print_r(sqlsrv_errors(), true)); }

  $rows = [];
  while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) $rows[] = $r;
  sqlsrv_free_stmt($st);
  return $rows;
}

// Kardex por producto (promedio ponderado móvil)
function procesarKardexPorProducto($conn, $idCodigo, $desde, $hasta) {
  $precioBase = costoProducto($conn, $idCodigo);
  $saldo = saldoInicial($conn, $idCodigo, $desde);
  $movs  = movimientosPeriodo($conn, $idCodigo, $desde, $hasta);

  $rows = [];
  $qty  = $saldo['qty'];
  $avg  = $saldo['avgCost'] ?: $precioBase;

  $totales = [
    'entradas_cant' => 0.0, 'entradas_importe' => 0.0,
    'salidas_cant'  => 0.0, 'salidas_importe'  => 0.0
  ];

  // Saldo inicial
  $rows[] = [
    'fecha' => $desde, 'tipo' => 'Saldo inicial', 'idCodigo'=>$idCodigo,
    'entrada_cant' => 0, 'entrada_costou'=>0,
    'salida_cant'  => 0, 'salida_costou'=>0,
    'saldo_cant'   => $qty, 'saldo_costou' => $avg
  ];

  foreach ($movs as $m) {
    $fecha = $m['fecha'];
    $tipo  = $m['tipo'];
    $cant  = floatval($m['cantidad']);

    if ($tipo === 'E') {
      $cu = $precioBase; // costo por unidad de la entrada
      $importeEntrada = $cant * $cu;

      // promedio ponderado
      $importeExistente = $qty * $avg;
      $qty = $qty + $cant;
      $avg = ($qty > 0) ? (($importeExistente + $importeEntrada) / $qty) : $avg;

      $totales['entradas_cant']    += $cant;
      $totales['entradas_importe'] += $importeEntrada;

      $rows[] = [
        'fecha' => $fecha, 'tipo' => 'Entrada', 'idCodigo'=>$idCodigo,
        'entrada_cant' => $cant, 'entrada_costou'=>$cu,
        'salida_cant'  => 0, 'salida_costou'=>0,
        'saldo_cant'   => $qty, 'saldo_costou' => $avg
      ];
    } else { // Salida
      $cu = $avg; // salida al costo promedio vigente
      $importeSalida = $cant * $cu;
      $qty = max(0.0, $qty - $cant);

      $totales['salidas_cant']    += $cant;
      $totales['salidas_importe'] += $importeSalida;

      $rows[] = [
        'fecha' => $fecha, 'tipo' => 'Salida', 'idCodigo'=>$idCodigo,
        'entrada_cant' => 0, 'entrada_costou'=>0,
        'salida_cant'  => $cant, 'salida_costou'=>$cu,
        'saldo_cant'   => $qty, 'saldo_costou' => $avg
      ];
    }
  }

  $totales['saldo_final_cant']   = $qty;
  $totales['saldo_final_costou'] = $avg;
  $totales['kardex_total']       = $totales['salidas_importe']; // costo de salidas

  return [$rows, $totales];
}

// HTML para un producto
function render_kardex_html($conn, $productoInfo, $desde, $hasta, $rows, $totales) {
  $p = $productoInfo;
  ob_start(); ?>
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
    h2,h3 { margin: 0 0 6px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid #ccc; padding: 6px; text-align: right; }
    th:first-child, td:first-child, th:nth-child(2), td:nth-child(2) { text-align: left; }
    tfoot td { font-weight: bold; }
  </style>
  <h2>Kardex de producto</h2>
  <h3><?= htmlspecialchars($p['idCodigo'].' - '.$p['descripcion'].(empty($p['codigo'])?'':' ('.$p['codigo'].')')) ?></h3>
  <div>Línea: <?= htmlspecialchars($p['linea'] ?? '') ?> | Sublinea: <?= htmlspecialchars($p['sublinea'] ?? '') ?></div>
  <div>Periodo: <?= htmlspecialchars($desde) ?> a <?= htmlspecialchars($hasta) ?></div>

  <table>
    <thead>
      <tr>
        <th>Fecha</th><th>Movimiento</th>
        <th>Ent. Cant</th><th>Ent. Costo U.</th>
        <th>Sal. Cant</th><th>Sal. Costo U.</th>
        <th>Saldo Cant</th><th>Saldo Costo U.</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= is_object($r['fecha']) ? $r['fecha']->format('Y-m-d H:i') : htmlspecialchars($r['fecha']) ?></td>
          <td><?= htmlspecialchars($r['tipo']) ?></td>
          <td><?= number_format($r['entrada_cant'], 2) ?></td>
          <td><?= number_format($r['entrada_costou'], 2) ?></td>
          <td><?= number_format($r['salida_cant'], 2) ?></td>
          <td><?= number_format($r['salida_costou'], 2) ?></td>
          <td><?= number_format($r['saldo_cant'], 2) ?></td>
          <td><?= number_format($r['saldo_costou'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="2">Totales</td>
        <td><?= number_format($totales['entradas_cant'], 2) ?></td>
        <td><?= number_format($totales['entradas_importe'], 2) ?></td>
        <td><?= number_format($totales['salidas_cant'], 2) ?></td>
        <td><?= number_format($totales['salidas_importe'], 2) ?></td>
        <td><?= number_format($totales['saldo_final_cant'], 2) ?></td>
        <td><?= number_format($totales['saldo_final_costou'], 2) ?></td>
      </tr>
      <tr>
        <td colspan="8">Costo total del Kardex (salidas valoradas): <?= number_format($totales['kardex_total'], 2) ?></td>
      </tr>
    </tfoot>
  </table>
  <?php
  return ob_get_clean();
}

// Inserta notificación de reporte generado (usa tu tabla Notificaciones)
function notificar_kardex($conn, $idCodigo, $desde, $hasta, $usuario, $idRolObjetivo = 1) {
  // columnas esperadas: idRol, descripcion, fecha, solicitudRevisada, cantidad, idCodigo
  $sql = "INSERT INTO dbo.Notificaciones (idRol, descripcion, fecha, solicitudRevisada, cantidad, idCodigo)
          VALUES (?, ?, GETDATE(), 0, NULL, ?)";
  $desc = "Reporte Kardex generado por {$usuario} para ".($idCodigo==='ALL'?'TODOS':$idCodigo)." del {$desde} al {$hasta}.";
  $params = [$idRolObjetivo, $desc, ($idCodigo==='ALL'?null:$idCodigo)];
  $stmt = sqlsrv_query($conn, $sql, $params);
  if ($stmt) sqlsrv_free_stmt($stmt);
}
