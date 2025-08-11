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
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit();
}

// Datos POST
$idHerramienta      = (int)($_POST['idHerramienta'] ?? 0);
$identificadorUnico = trim($_POST['identificadorUnico'] ?? ''); // opcional, por si el front lo manda
$observaciones      = trim($_POST['observaciones'] ?? '');
$estado             = trim($_POST['estado'] ?? '');
$fechaRetorno       = $_POST['fechaRetorno'] ?? date('Y-m-d');
$registradoPor      = (int)($_POST['registradoPor'] ?? $idRol); // aquí guardamos el idRol, como pediste

if ($estado === '') {
    echo json_encode(['success' => false, 'message' => 'Estado requerido']);
    exit();
}

// Normaliza fecha a DATETIME
$fechaRetornoDT = date('Y-m-d H:i:s', strtotime($fechaRetorno));

// Si no vino idHerramienta, intenta resolverlo por identificadorUnico
if ($idHerramienta <= 0 && $identificadorUnico !== '') {
    $sqlFind = "SELECT idHerramienta FROM HerramientasUnicas WHERE identificadorUnico = ?";
    $stmtFind = sqlsrv_query($conn, $sqlFind, [$identificadorUnico]);
    if ($stmtFind && ($r = sqlsrv_fetch_array($stmtFind, SQLSRV_FETCH_ASSOC))) {
        $idHerramienta = (int)$r['idHerramienta'];
    }
}

// Validación fuerte
if ($idHerramienta <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos (herramienta)']);
    exit();
}

// Trae info (idCodigo y enInventario) para actualizar inventario
$sqlInfo = "SELECT idCodigo, enInventario FROM HerramientasUnicas WHERE idHerramienta = ?";
$stmtInfo = sqlsrv_query($conn, $sqlInfo, [$idHerramienta]);
if ($stmtInfo === false) {
    echo json_encode(['success' => false, 'message' => 'Error consultando herramienta']);
    exit();
}
$info = sqlsrv_fetch_array($stmtInfo, SQLSRV_FETCH_ASSOC);
if (!$info) {
    echo json_encode(['success' => false, 'message' => 'Herramienta no encontrada']);
    exit();
}
$idCodigo = (int)$info['idCodigo'];

// Transacción
if (!sqlsrv_begin_transaction($conn)) {
    echo json_encode(['success' => false, 'message' => 'No se pudo iniciar la transacción']);
    exit();
}

try {
    // 1) Marcar herramienta como devuelta SOLO si estaba fuera (enInventario=0)
    $sqlUpdHerr = "UPDATE HerramientasUnicas
                   SET enInventario = 1, estadoActual = ?
                   WHERE idHerramienta = ? AND enInventario = 0";
    $stmtUpdHerr = sqlsrv_query($conn, $sqlUpdHerr, [$estado, $idHerramienta]);
    if ($stmtUpdHerr === false) {
        throw new Exception('Error actualizando herramienta');
    }
    $afectadas = sqlsrv_rows_affected($stmtUpdHerr);

    if ($afectadas === 0) {
        // No se actualizó: veamos si no existe o ya estaba en 1
        $sqlChk = "SELECT enInventario FROM HerramientasUnicas WHERE idHerramienta = ?";
        $stmtChk = sqlsrv_query($conn, $sqlChk, [$idHerramienta]);
        if ($stmtChk && ($c = sqlsrv_fetch_array($stmtChk, SQLSRV_FETCH_ASSOC))) {
            if ((int)$c['enInventario'] === 1) {
                throw new Exception('La herramienta ya está en inventario');
            } else {
                throw new Exception('Herramienta no encontrada (verifique el ID)');
            }
        }
        throw new Exception('Herramienta no encontrada');
    }

    // 2) Insertar en Devoluciones
    $sqlDev = "INSERT INTO Devoluciones (idHerramienta, observaciones, estado, fechaRetorno, registradoPor)
               VALUES (?, ?, ?, ?, ?)";
    $paramsDev = [$idHerramienta, $observaciones, $estado, $fechaRetornoDT, $registradoPor];
    $stmtDev = sqlsrv_query($conn, $sqlDev, $paramsDev);
    if ($stmtDev === false) {
        throw new Exception('Error insertando en Devoluciones');
    }

    // 3) Actualizar Inventario (+1)
    $sqlSelInv = "SELECT cantidadActual FROM Inventario WHERE idCodigo = ?";
    $stmtSelInv = sqlsrv_query($conn, $sqlSelInv, [$idCodigo]);
    if ($stmtSelInv === false) {
        throw new Exception('Error consultando Inventario');
    }
    $inv = sqlsrv_fetch_array($stmtSelInv, SQLSRV_FETCH_ASSOC);

    $hoy = date('Y-m-d H:i:s');
    if ($inv) {
        $nuevaCant = (int)$inv['cantidadActual'] + 1;
        $sqlUpdInv = "UPDATE Inventario SET cantidadActual = ?, ultimaActualizacion = ? WHERE idCodigo = ?";
        $stmtUpdInv = sqlsrv_query($conn, $sqlUpdInv, [$nuevaCant, $hoy, $idCodigo]);
        if ($stmtUpdInv === false) {
            throw new Exception('Error actualizando Inventario');
        }
    } else {
        $idCaja = 1; $ubicacion = "Almacen";
        $sqlInsInv = "INSERT INTO Inventario (idCodigo, idCaja, cantidadActual, ubicacion, ultimaActualizacion)
                      VALUES (?, ?, ?, ?, ?)";
        $stmtInsInv = sqlsrv_query($conn, $sqlInsInv, [$idCodigo, $idCaja, 1, $ubicacion, $hoy]);
        if ($stmtInsInv === false) {
            throw new Exception('Error insertando Inventario');
        }
    }

    if (!sqlsrv_commit($conn)) {
        throw new Exception('Error en commit');
    }

    echo json_encode(['success' => true]);

} catch (Exception $ex) {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
}
