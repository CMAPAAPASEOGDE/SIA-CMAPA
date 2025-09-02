<?php
// reportes_whms_utils.php
// Funciones comunes para consultar y exportar Movimientos de Almacén

function db_conn_or_die() {
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
        die("Error de conexión: " . print_r(sqlsrv_errors(), true));
    }
    return $conn;
}

/**
 * Devuelve [Y-m-d 00:00:00, (end+1 día) Y-m-d 00:00:00] para filtrar con BETWEEN >= start AND < endNext
 */
function range_from_month_year($month, $year) {
    $month = (int)$month;
    $year  = (int)$year;
    if ($month < 1 || $month > 12 || $year < 2000) {
        // Si vienen raros, usar mes/año actuales
        $month = (int)date('m');
        $year  = (int)date('Y');
    }
    $start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
    // último día del mes
    $lastDay = (int)date('t', strtotime("$year-$month-01"));
    $endNext = date('Y-m-d 00:00:00', strtotime(sprintf('%04d-%02d-%02d +1 day', $year, $month, $lastDay)));
    return [$start, $endNext];
}

/**
 * Inserta notificación simple para admins (idRol=1 por defecto)
 */
function insert_notification($conn, $descripcion, $idCodigo = null, $cantidad = null, $idRol = 1) {
    $sql = "INSERT INTO dbo.Notificaciones (idRol, descripcion, fecha, solicitudRevisada, cantidad, idCodigo)
            VALUES (?, ?, GETDATE(), 0, ?, ?)";
    $params = [$idRol, $descripcion, $cantidad, $idCodigo];
    sqlsrv_query($conn, $sql, $params);
}

/**
 * Obtiene catálogo de productos para poblar selects
 */
function get_product_catalog($conn) {
    $sql = "SELECT p.idCodigo, p.codigo, p.descripcion 
            FROM dbo.Productos p
            WHERE p.estatus IS NULL OR p.estatus <> 'INACTIVO'
            ORDER BY p.codigo";
    $stmt = sqlsrv_query($conn, $sql);
    $rows = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
    return $rows;
}

/**
 * Consulta unificado de movimientos (sin costos).
 * Devuelve arreglo de filas normalizadas:
 *   fecha, tipoMovimiento, sku, descripcion, cantidad, idCodigo, idHerramienta, identificadorUnico, detalle
 * Filtros: por mes/año y opcionalmente por idCodigo.
 */
function fetch_movimientos_almacen($conn, $month, $year, $idCodigo = null) {
    list($start, $endNext) = range_from_month_year($month, $year);
    $paramsCommon = [$start, $endNext];

    // -------- ENTRADAS --------
    $sqlEnt = "SELECT 
                e.fecha AS fecha,
                CAST('ENTRADA' AS VARCHAR(40)) AS tipoMovimiento,
                p.codigo AS sku,
                p.descripcion AS descripcion,
                e.cantidad AS cantidad,
                e.idCodigo,
                e.idHerramienta,
                hu.identificadorUnico,
                CAST(NULL AS VARCHAR(500)) AS detalle
              FROM dbo.Entradas e
              INNER JOIN dbo.Productos p ON p.idCodigo = e.idCodigo
              LEFT JOIN dbo.HerramientasUnicas hu ON hu.idHerramienta = e.idHerramienta
              WHERE e.fecha >= ? AND e.fecha < ?";

    $paramsEnt = $paramsCommon;
    if (!empty($idCodigo)) {
        $sqlEnt .= " AND e.idCodigo = ?";
        $paramsEnt[] = $idCodigo;
    }

    // -------- SALIDAS ORDEN (NO HERRAMIENTA) --------
    $sqlSal = "SELECT 
                s.fechaSalida AS fecha,
                CAST('SALIDA (ORDEN)' AS VARCHAR(40)) AS tipoMovimiento,
                p.codigo AS sku,
                p.descripcion AS descripcion,
                s.cantidad AS cantidad,
                s.idCodigo,
                NULL AS idHerramienta,
                NULL AS identificadorUnico,
                CONCAT('Orden: ', COALESCE(s.numeroOrden,''), 
                       ' | RPU: ', COALESCE(s.rpuUsuario,''), 
                       ' | Operador: ', COALESCE(CAST(s.idOperador AS VARCHAR(20)),'')
                ) AS detalle
              FROM dbo.Salidas s
              INNER JOIN dbo.Productos p ON p.idCodigo = s.idCodigo
              WHERE s.idHerramienta IS NULL
                AND s.fechaSalida >= ? AND s.fechaSalida < ?";

    $paramsSal = $paramsCommon;
    if (!empty($idCodigo)) {
        $sqlSal .= " AND s.idCodigo = ?";
        $paramsSal[] = $idCodigo;
    }

    // -------- SALIDAS SIN ORDEN (NO HERRAMIENTA) --------
    $sqlSso = "SELECT 
                so.fecha AS fecha,
                CAST('SALIDA (SIN ORDEN)' AS VARCHAR(40)) AS tipoMovimiento,
                p.codigo AS sku,
                p.descripcion AS descripcion,
                so.cantidad AS cantidad,
                so.idCodigo,
                NULL AS idHerramienta,
                NULL AS identificadorUnico,
                CONCAT('Área: ', COALESCE(so.areaSolicitante,''), 
                       ' | Encargado: ', COALESCE(so.encargadoArea,''), 
                       ' | Comentarios: ', COALESCE(so.comentarios,'')
                ) AS detalle
              FROM dbo.SalidaSinorden so
              INNER JOIN dbo.Productos p ON p.idCodigo = so.idCodigo
              WHERE so.idHerramienta IS NULL
                AND so.fecha >= ? AND so.fecha < ?";

    $paramsSso = $paramsCommon;
    if (!empty($idCodigo)) {
        $sqlSso .= " AND so.idCodigo = ?";
        $paramsSso[] = $idCodigo;
    }

    // -------- HERRAMIENTA PRESTADA (SALIDAS con idHerramienta) --------
    $sqlHP = "SELECT 
                s.fechaSalida AS fecha,
                CAST('HERRAMIENTA PRESTADA' AS VARCHAR(40)) AS tipoMovimiento,
                p.codigo AS sku,
                p.descripcion AS descripcion,
                s.cantidad AS cantidad,
                s.idCodigo,
                s.idHerramienta,
                hu.identificadorUnico,
                CONCAT('Orden: ', COALESCE(s.numeroOrden,''), 
                       ' | RPU: ', COALESCE(s.rpuUsuario,''), 
                       ' | Operador: ', COALESCE(CAST(s.idOperador AS VARCHAR(20)),'')
                ) AS detalle
              FROM dbo.Salidas s
              INNER JOIN dbo.Productos p ON p.idCodigo = s.idCodigo
              INNER JOIN dbo.HerramientasUnicas hu ON hu.idHerramienta = s.idHerramienta
              WHERE s.idHerramienta IS NOT NULL
                AND s.fechaSalida >= ? AND s.fechaSalida < ?";

    $paramsHP = $paramsCommon;
    if (!empty($idCodigo)) {
        $sqlHP .= " AND s.idCodigo = ?";
        $paramsHP[] = $idCodigo;
    }

    // -------- HERRAMIENTA DEVUELTA (Devoluciones) --------
    $sqlHD = "SELECT 
                d.fechaRetorno AS fecha,
                CAST('HERRAMIENTA DEVUELTA' AS VARCHAR(40)) AS tipoMovimiento,
                p.codigo AS sku,
                p.descripcion AS descripcion,
                CAST(1 AS INT) AS cantidad,
                hu.idCodigo,
                d.idHerramienta,
                hu.identificadorUnico,
                CONCAT('Estado: ', COALESCE(d.estado,''), 
                       ' | Obs: ', COALESCE(d.observaciones,''), 
                       ' | Registrado por: ', COALESCE(d.registradoPor,'')
                ) AS detalle
              FROM dbo.Devoluciones d
              INNER JOIN dbo.HerramientasUnicas hu ON hu.idHerramienta = d.idHerramienta
              INNER JOIN dbo.Productos p ON p.idCodigo = hu.idCodigo
              WHERE d.fechaRetorno >= ? AND d.fechaRetorno < ?";

    $paramsHD = $paramsCommon;
    if (!empty($idCodigo)) {
        $sqlHD .= " AND hu.idCodigo = ?";
        $paramsHD[] = $idCodigo;
    }

    // Ejecutar y unir resultados
    $rows = [];
    foreach ([[$sqlEnt, $paramsEnt], [$sqlSal, $paramsSal], [$sqlSso, $paramsSso], [$sqlHP, $paramsHP], [$sqlHD, $paramsHD]] as $pair) {
        list($sql, $params) = $pair;
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) continue;
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Normalizar fecha a string
            if ($r['fecha'] instanceof DateTime) {
                $r['fecha'] = $r['fecha']->format('Y-m-d H:i:s');
            }
            $rows[] = $r;
        }
    }

    // Ordenar por fecha asc
    usort($rows, function($a, $b) {
        return strcmp($a['fecha'], $b['fecha']);
    });

    return $rows;
}
