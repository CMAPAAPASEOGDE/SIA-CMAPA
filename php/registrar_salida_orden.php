<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: ../exitord.php"); exit(); }

// Conexión
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
  "Database"=>"db_sia","Uid"=>"cmapADMIN","PWD"=>"@siaADMN56*",
  "Encrypt"=>true,"TrustServerCertificate"=>false
];
$conn = sqlsrv_connect($serverName,$connectionOptions) or die(print_r(sqlsrv_errors(), true));

$rpu         = $_POST['rpuUsuario'] ?? '';
$orden       = $_POST['numeroOrden'] ?? '';
$comentarios = $_POST['comentarios'] ?? '';
$idOperador  = (int)($_POST['idOperador'] ?? 0);
$fecha       = date('Y-m-d H:i:s');
$elementos   = $_POST['elementos'] ?? [];

if (strlen($rpu)!==12 || !ctype_digit($rpu) || $orden==='' || $idOperador===0 || empty($elementos)) {
  header("Location: ../exitord.php"); exit();
}

sqlsrv_begin_transaction($conn);

try {
  foreach ($elementos as $el) {
    $idCodigo = (int)($el['idCodigo'] ?? 0);
    $cantidad = (int)($el['cantidad'] ?? 0);
    if ($idCodigo===0 || $cantidad<=0) { throw new Exception('Datos inválidos'); }

    // Stock total
    $stmt = sqlsrv_query($conn, "SELECT SUM(cantidadActual) AS stock FROM Inventario WHERE idCodigo = ?", [$idCodigo]);
    if (!$stmt) throw new Exception('Error stock');
    $row  = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    $stock = (float)($row['stock'] ?? 0);
    if ($stock < $cantidad) { throw new Exception('Stock insuficiente'); }

    // ¿Se controla por serie?
    $stmt = sqlsrv_query($conn, "SELECT TOP 1 1 AS hay FROM HerramientasUnicas WHERE idCodigo = ? AND enInventario = 1", [$idCodigo]);
    if (!$stmt) throw new Exception('Error series');
    $rowSerie = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    $requiereSerie = (bool)($rowSerie['hay'] ?? false);

    $toolIds = [];
    if ($requiereSerie) {
      $stmt = sqlsrv_query(
        $conn,
        "SELECT TOP (?) idHerramienta FROM HerramientasUnicas WHERE idCodigo = ? AND enInventario = 1 ORDER BY idHerramienta ASC",
        [$cantidad, $idCodigo]
      );
      if (!$stmt) throw new Exception('Error obt series');
      while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $toolIds[] = (int)$r['idHerramienta']; }
      sqlsrv_free_stmt($stmt);
      if (count($toolIds) < $cantidad) { throw new Exception('Sin suficientes series'); }
    }

    // Inserta salida
    $ok = sqlsrv_query(
      $conn,
      "INSERT INTO Salidas (rpuUsuario, numeroOrden, comentarios, idOperador, idCodigo, cantidad, fechaSalida)
       VALUES (?, ?, ?, ?, ?, ?, ?)",
      [$rpu, $orden, $comentarios, $idOperador, $idCodigo, $cantidad, $fecha]
    );
    if (!$ok) throw new Exception('Error insert salida');

    // Descontar Inventario repartiendo por filas (FIFO simple)
    $porDescontar = $cantidad;
    $stmt = sqlsrv_query(
      $conn,
      "SELECT idInventario, cantidadActual
         FROM Inventario
        WHERE idCodigo = ? AND cantidadActual > 0
     ORDER BY ultimaActualizacion ASC, idInventario ASC",
      [$idCodigo]
    );
    if (!$stmt) throw new Exception('Error leer inventario');

    $filas = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $filas[] = $r; }
    sqlsrv_free_stmt($stmt);

    foreach ($filas as $f) {
      if ($porDescontar <= 0) break;
      $usar = min($porDescontar, (float)$f['cantidadActual']);
      $ok = sqlsrv_query(
        $conn,
        "UPDATE Inventario
            SET cantidadActual = cantidadActual - ?, ultimaActualizacion = ?
          WHERE idInventario = ?",
        [$usar, $fecha, (int)$f['idInventario']]
      );
      if (!$ok) throw new Exception('Error update inventario');
      $porDescontar -= $usar;
    }
    if ($porDescontar > 0) { throw new Exception('Descuadre inventario'); }

    // Marcar series fuera de inventario (si aplica)
    if ($requiereSerie) {
      foreach ($toolIds as $tid) {
        $ok = sqlsrv_query($conn, "UPDATE HerramientasUnicas SET enInventario = 0 WHERE idHerramienta = ?", [$tid]);
        if (!$ok) throw new Exception('Error update serie');
      }
    }
  }

  sqlsrv_commit($conn);
  header("Location: ../exitordcnf.php"); exit();

} catch (Throwable $e) {
  sqlsrv_rollback($conn);
  header("Location: ../exitord2.php"); // tu vista de error
  exit();
}
