<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1, 2], true)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit();
}

// Conexión SQL Server
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión', 'detail' => sqlsrv_errors()]);
    exit();
}

// Datos POST
$idHerramienta = (int)($_POST['idHerramienta'] ?? 0);
$observaciones = trim($_POST['observaciones'] ?? '');
$estado        = trim($_POST['estado'] ?? '');
$fechaRetorno  = $_POST['fechaRetorno'] ?? date('Y-m-d');
$registradoPor = (int)($_POST['registradoPor'] ?? $idRol); // tomamos el rol

if ($idHerramienta <= 0 || $estado === '') {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
    exit();
}

// Normaliza fecha a DATETIME
$fechaRetornoDT = date('Y-m-d H:i:s', strtotime($fechaRetorno));

// 1) Obtener info de la herramienta (idCodigo, enInventario) y producto
$sqlInfo = "SELECT H.idHerramienta, H.idCodigo, H.enInventario, H.identificadorUnico,
                   P.descripcion
            FROM HerramientasUnicas H
            INNER JOIN Productos P ON P.idCodigo = H.idCodigo
            WHERE H.idHerramienta = ?";
$stmtInfo = sqlsrv_query($conn, $sqlInfo, [$idHerramienta]);
if ($stmtInfo === false) {
    echo json_encode(['success' => false, 'message' => 'Error consultando herramienta', 'detail' => sqlsrv_errors()]);
    exit();
}
$info = sqlsrv_fetch_array($stmtInfo, SQLSRV_FETCH_ASSOC);
if (!$info) {
    echo json_encode(['success' => false, 'message' => 'Herramienta no encontrada']);
    exit();
}
$idCodigo = (int)$info['idCodigo'];

// Verifica que realmente esté fuera (enInventario=0)
if ((int)$info['enInventario'] !== 0) {
    echo json_encode(['success' => false, 'message' => 'La herramienta ya está en inventario']);
    exit();
}

// 2) Transacción
if (!sqlsrv_begin_transaction($conn)) {
    echo json_encode(['success' => false, 'message' => 'No se pudo iniciar la transacción', 'detail' => sqlsrv_errors()]);
    exit();
}

try {
    // 3) Insertar en Devoluciones
    $sqlDev = "INSERT INTO Devoluciones (idHerramienta, observaciones, estado, fechaRetorno, registradoPor)
               VALUES (?, ?, ?, ?, ?)";
    $paramsDev = [$idHerramienta, $observaciones, $estado, $fechaRetornoDT, $registradoPor];
    $stmtDev = sqlsrv_query($conn, $sqlDev, $paramsDev);
    if ($stmtDev === false) {
        throw new Exception('Error insertando en Devoluciones: ' . print_r(sqlsrv_errors(), true));
    }

    // 4) Marcar herramienta como devuelta (enInventario=1) y actualizar estadoActual
    $sqlUpdHerr = "UPDATE HerramientasUnicas
                   SET enInventario = 1, estadoActual = ?
                   WHERE idHerramienta = ?";
    $stmtUpdHerr = sqlsrv_query($conn, $sqlUpdHerr, [$estado, $idHerramienta]);
    if ($stmtUpdHerr === false) {
        throw new Exception('Error actualizando herramienta: ' . print_r(sqlsrv_errors(), true));
    }

    // 5) Actualizar Inventario (+1 para ese idCodigo)
    $sqlSelInv = "SELECT cantidadActual FROM Inventario WHERE idCodigo = ?";
    $stmtSelInv = sqlsrv_query($conn, $sqlSelInv, [$idCodigo]);
    if ($stmtSelInv === false) {
        throw new Exception('Error consultando Inventario: ' . print_r(sqlsrv_errors(), true));
    }
    $inv = sqlsrv_fetch_array($stmtSelInv, SQLSRV_FETCH_ASSOC);

    $hoy = date('Y-m-d H:i:s');
    if ($inv) {
        $nuevaCant = (int)$inv['cantidadActual'] + 1;
        $sqlUpdInv = "UPDATE Inventario SET cantidadActual = ?, ultimaActualizacion = ?
                      WHERE idCodigo = ?";
        $stmtUpdInv = sqlsrv_query($conn, $sqlUpdInv, [$nuevaCant, $hoy, $idCodigo]);
        if ($stmtUpdInv === false) {
            throw new Exception('Error actualizando Inventario: ' . print_r(sqlsrv_errors(), true));
        }
    } else {
        // Si no existiera (raro), inserta con 1
        $idCaja = 1; $ubicacion = "Almacen";
        $sqlInsInv = "INSERT INTO Inventario (idCodigo, idCaja, cantidadActual, ubicacion, ultimaActualizacion)
                      VALUES (?, ?, ?, ?, ?)";
        $stmtInsInv = sqlsrv_query($conn, $sqlInsInv, [$idCodigo, $idCaja, 1, $ubicacion, $hoy]);
        if ($stmtInsInv === false) {
            throw new Exception('Error insertando Inventario: ' . print_r(sqlsrv_errors(), true));
        }
    }

    // 6) Commit
    if (!sqlsrv_commit($conn)) {
        throw new Exception('Error en commit: ' . print_r(sqlsrv_errors(), true));
    }

    echo json_encode(['success' => true]);

} catch (Exception $ex) {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
}
