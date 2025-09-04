<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit();
}

// Conexión
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
    "Database" => "db_sia",
    "Uid"      => "cmapADMIN",
    "PWD"      => "@siaADMN56*",
    "Encrypt"  => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) die(print_r(sqlsrv_errors(), true));

// Datos
$idCodigo   = (int)($_POST['idCodigo'] ?? 0);
$cantidad   = (int)($_POST['cantidad'] ?? 0);
$fechaIn    = trim($_POST['fecha'] ?? '');
$comentarios= "MOVIMIENTO DE ADMINISTRADOR";
$usuarioAdm = (string)($_SESSION['usuario'] ?? 'ADMIN');

// Normaliza fecha (si viene solo YYYY-MM-DD, agrega hora)
if ($fechaIn === '') {
    $fecha = date('Y-m-d H:i:s');
} else {
    $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaIn)
           ? $fechaIn.' 00:00:00'
           : date('Y-m-d H:i:s', strtotime($fechaIn));
}

// Validación
if ($idCodigo === 0 || $cantidad <= 0) {
    header("Location: ../admnedtext.php");
    exit();
}

sqlsrv_begin_transaction($conn);

try {
    // 1) Stock total (sumando todas las filas del inventario para ese código)
    $stmt = sqlsrv_query(
        $conn,
        "SELECT SUM(cantidadActual) AS stock FROM Inventario WHERE idCodigo = ?",
        [$idCodigo]
    );
    if (!$stmt) throw new Exception('Error al consultar stock.');
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    $stockTotal = (float)($row['stock'] ?? 0);
    if ($stockTotal < $cantidad) {
        throw new Exception('Stock insuficiente.');
    }

    // 2) ¿Se controla por serie? (sólo si existen series en inventario para este código)
    $stmt = sqlsrv_query(
        $conn,
        "SELECT TOP 1 1 AS hay FROM HerramientasUnicas WHERE idCodigo = ? AND enInventario = 1",
        [$idCodigo]
    );
    if (!$stmt) throw new Exception('Error al revisar series.');
    $rowSerie = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    $requiereSerie = (bool)($rowSerie['hay'] ?? false);

    $toolIds = [];
    if ($requiereSerie) {
        // Toma exactamente N series disponibles
        $stmt = sqlsrv_query(
            $conn,
            "SELECT TOP (?) idHerramienta
               FROM HerramientasUnicas
              WHERE idCodigo = ? AND enInventario = 1
           ORDER BY idHerramienta ASC",
            [$cantidad, $idCodigo]
        );
        if (!$stmt) throw new Exception('Error al obtener series.');
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $toolIds[] = (int)$r['idHerramienta'];
        }
        sqlsrv_free_stmt($stmt);

        if (count($toolIds) < $cantidad) {
            throw new Exception('No hay suficientes series disponibles.');
        }
    }

    // 3) Inserta la salida (usamos la misma tabla que tus “sin orden”)
    $stmt = sqlsrv_query(
        $conn,
        "INSERT INTO SalidaSinorden (areaSolicitante, encargadoArea, fecha, comentarios, idCodigo, cantidad)
         VALUES (?, ?, ?, ?, ?, ?)",
        ['ADMIN', $usuarioAdm, $fecha, $comentarios, $idCodigo, $cantidad]
    );
    if (!$stmt) throw new Exception('Error al insertar salida.');
    sqlsrv_free_stmt($stmt);

    // 4) Descontar del Inventario repartiendo por filas (FIFO simple)
    $porDescontar = $cantidad;

    $stmt = sqlsrv_query(
        $conn,
        "SELECT idInventario, cantidadActual
           FROM Inventario
          WHERE idCodigo = ? AND cantidadActual > 0
       ORDER BY ultimaActualizacion ASC, idInventario ASC",
        [$idCodigo]
    );
    if (!$stmt) throw new Exception('Error al leer filas de inventario.');

    $filas = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $filas[] = $r;
    }
    sqlsrv_free_stmt($stmt);

    foreach ($filas as $f) {
        if ($porDescontar <= 0) break;
        $aplicar = min($porDescontar, (float)$f['cantidadActual']);

        $ok = sqlsrv_query(
            $conn,
            "UPDATE Inventario
                SET cantidadActual = cantidadActual - ?, ultimaActualizacion = ?
              WHERE idInventario = ?",
            [$aplicar, $fecha, (int)$f['idInventario']]
        );
        if (!$ok) throw new Exception('Error al actualizar inventario.');
        $porDescontar -= $aplicar;
    }
    if ($porDescontar > 0) {
        throw new Exception('No se pudo descontar todo el inventario (descuadre).');
    }

    // 5) Marcar series como fuera de inventario (si aplica)
    if ($requiereSerie) {
        foreach ($toolIds as $tid) {
            $ok = sqlsrv_query(
                $conn,
                "UPDATE HerramientasUnicas SET enInventario = 0 WHERE idHerramienta = ?",
                [$tid]
            );
            if (!$ok) throw new Exception('Error al actualizar series.');
        }
    }

    sqlsrv_commit($conn);
    header("Location: ../admnedtextcf.php");
    exit();

} catch (Throwable $e) {
    sqlsrv_rollback($conn);
    // Si quieres depurar temporalmente:
    // die($e->getMessage());
    header("Location: ../admnedtexter2.php");
    exit();
}
