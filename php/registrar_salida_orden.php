<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: ../exitord.php"); exit(); }

// Conexión
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
  "Database" => "db_sia",
  "Uid" => "cmapADMIN",
  "PWD" => "@siaADMN56*",
  "Encrypt" => true, "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) { die(print_r(sqlsrv_errors(), true)); }

// Helpers ---------------

function total_stock(sqlsrv $conn, int $idCodigo): float {
  $stmt = sqlsrv_query($conn, "SELECT SUM(cantidadActual) AS total FROM Inventario WHERE idCodigo = ?", [$idCodigo]);
  if (!$stmt) return 0;
  $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
  sqlsrv_free_stmt($stmt);
  return (float)($row['total'] ?? 0);
}

function es_unica(sqlsrv $conn, int $idCodigo): bool {
  // 1) preferir el tipo del producto
  $stmt = sqlsrv_query($conn, "SELECT TOP 1 tipo FROM Productos WHERE idCodigo = ?", [$idCodigo]);
  $tipo = '';
  if ($stmt) {
    $r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $tipo = strtoupper(trim((string)($r['tipo'] ?? '')));
    sqlsrv_free_stmt($stmt);
  }
  if (in_array($tipo, ['H','HERRAMIENTA','HERRAMIENTAS','UNICA','ÚNICA','UNICO','ÚNICO'], true)) {
    return true;
  }
  // 2) si no hay tipado consistente, como fallback revisa si existen registros únicos
  $stmt2 = sqlsrv_query($conn, "SELECT COUNT(*) AS c FROM HerramientasUnicas WHERE idCodigo = ?", [$idCodigo]);
  if ($stmt2) {
    $r2 = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt2);
    return ((int)($r2['c'] ?? 0) > 0);
  }
  return false;
}

/** descuenta 'cantidad' del inventario repartiendo entre filas (si existen varias cajas) */
function descontar_inventario(sqlsrv $conn, int $idCodigo, int $cantidad, string $fecha): bool {
  $restante = $cantidad;
  $stmt = sqlsrv_query($conn,
    "SELECT idInventario, cantidadActual
       FROM Inventario
      WHERE idCodigo = ? AND cantidadActual > 0
   ORDER BY cantidadActual DESC, idInventario ASC",
    [$idCodigo]
  );
  if (!$stmt) return false;

  while ($restante > 0 && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    $idInv   = (int)$row['idInventario'];
    $enFila  = (float)$row['cantidadActual'];
    if ($enFila <= 0) continue;

    $quita = (int)min($restante, $enFila);
    $ok = sqlsrv_query(
      $conn,
      "UPDATE Inventario
          SET cantidadActual = cantidadActual - ?, ultimaActualizacion = ?
        WHERE idInventario = ?",
      [$quita, $fecha, $idInv]
    );
    if (!$ok) return false;

    $restante -= $quita;
  }
  sqlsrv_free_stmt($stmt);
  return $restante === 0;
}

// -----------------------------------------------

$rpu         = $_POST['rpuUsuario']   ?? '';
$orden       = $_POST['numeroOrden']  ?? '';
$comentarios = $_POST['comentarios']  ?? '';
$idOperador  = (int)($_POST['idOperador'] ?? 0);
$fecha       = date('Y-m-d H:i:s');

if (strlen($rpu) !== 12 || !ctype_digit($rpu) || empty($orden) || $idOperador === 0) {
  header("Location: ../exitord.php"); exit();
}

$elementos = $_POST['elementos'] ?? [];
if (empty($elementos) || !is_array($elementos)) { header("Location: ../exitord.php"); exit(); }

sqlsrv_begin_transaction($conn);

foreach ($elementos as $el) {
  $idCodigo = (int)($el['idCodigo'] ?? 0);
  $cantidad = (int)($el['cantidad'] ?? 0);
  if ($idCodigo === 0 || $cantidad <= 0) { sqlsrv_rollback($conn); header("Location: ../exitord.php"); exit(); }

  // 1) Stock total
  $total = total_stock($conn, $idCodigo);
  if ($total < $cantidad) { sqlsrv_rollback($conn); header("Location: ../exitorder2.php"); exit(); }

  // 2) Si es herramienta única, validar y preparar ids
  $toolIds = [];
  if (es_unica($conn, $idCodigo)) {
    $stmtTools = sqlsrv_query(
      $conn,
      "SELECT TOP (?) idHerramienta
         FROM HerramientasUnicas
        WHERE idCodigo = ? AND enInventario = 1
     ORDER BY idHerramienta ASC",
      [$cantidad, $idCodigo]
    );
    if (!$stmtTools) { sqlsrv_rollback($conn); die(print_r(sqlsrv_errors(), true)); }

    while ($t = sqlsrv_fetch_array($stmtTools, SQLSRV_FETCH_ASSOC)) {
      $toolIds[] = (int)$t['idHerramienta'];
    }
    sqlsrv_free_stmt($stmtTools);

    if (count($toolIds) < $cantidad) {
      sqlsrv_rollback($conn); header("Location: ../exitorder2.php"); exit();
    }
  }

  // 3) Registrar salida
  $ok = sqlsrv_query(
    $conn,
    "INSERT INTO Salidas (rpuUsuario, numeroOrden, comentarios, idOperador, idCodigo, cantidad, fechaSalida)
     VALUES (?, ?, ?, ?, ?, ?, ?)",
    [$rpu, $orden, $comentarios, $idOperador, $idCodigo, $cantidad, $fecha]
  );
  if (!$ok) { sqlsrv_rollback($conn); die(print_r(sqlsrv_errors(), true)); }

  // 4) Descontar inventario repartiendo
  if (!descontar_inventario($conn, $idCodigo, $cantidad, $fecha)) {
    sqlsrv_rollback($conn); header("Location: ../exitorder2.php"); exit();
  }

  // 5) Marcar herramientas únicas (solo si aplica)
  if ($toolIds) {
    foreach ($toolIds as $hid) {
      $ok2 = sqlsrv_query($conn, "UPDATE HerramientasUnicas SET enInventario = 0 WHERE idHerramienta = ?", [$hid]);
      if (!$ok2) { sqlsrv_rollback($conn); die(print_r(sqlsrv_errors(), true)); }
    }
  }
}

sqlsrv_commit($conn);
header("Location: ../exitordcnf.php");
exit();
