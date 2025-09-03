<?php
// reportes_whms_utils.php

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
        error_log("Database connection failed: " . print_r(sqlsrv_errors(), true));
        die("Error de conexión a la base de datos");
    }
    return $conn;
}

function get_product_catalog($conn) {
    $productos = [];
    $sql = "SELECT idCodigo, codigo, descripcion FROM dbo.Productos ORDER BY descripcion";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $productos[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    return $productos;
}

function range_from_month_year($mes, $anio) {
    $start   = sprintf('%04d-%02d-01 00:00:00', (int)$anio, (int)$mes);
    $endNext = date('Y-m-d 00:00:00', strtotime("$start +1 month"));
    return [$start, $endNext];
}

/**
 * Devuelve filas normalizadas:
 *   fecha, tipoMovimiento, sku, descripcion, cantidad, idCodigo, idHerramienta, identificadorUnico, detalle
 */
function fetch_movimientos_almacen($conn, $mes, $anio, $idCodigo = null) {
    list($start, $endNext) = range_from_month_year($mes, $anio);
    $paramsCommon = [$start, $endNext];

    // ENTRADAS
    $sqlEnt = "SELECT 
                e.fecha AS fecha,
                CAST('ENTRADA' AS VARCHAR(40)) AS tipoMovimiento,
                p.codigo AS sku,
                p.descripcion,
                e.cantidad,
                e.idCodigo,
                e.idHerramienta,
                hu.identificadorUnico,
                CONCAT('ProveedorId: ', COALESCE(CAST(e.idProveedor AS VARCHAR(20)),'') ) AS detalle
              FROM dbo.Entradas e
              INNER JOIN dbo.Productos p ON p.idCodigo = e.idCodigo
              LEFT  JOIN dbo.HerramientasUnicas hu ON hu.idHerramienta = e.idHerramienta
              WHERE e.fecha >= ? AND e.fecha < ?";
    $paramsEnt = $paramsCommon;
    if (!empty($idCodigo)) { $sqlEnt .= " AND e.idCodigo = ?"; $paramsEnt[] = $idCodigo; }

    // SALIDA (ORDEN) sin herramienta
    $sqlSal = "SELECT 
                s.fechaSalida AS fecha,
                CAST('SALIDA (ORDEN)' AS VARCHAR(40)) AS tipoMovimiento,
                p.codigo AS sku,
                p.descripcion,
                s.cantidad,
                s.idCodigo,
                NULL AS idHerramienta,
                NULL AS identificadorUnico,
                CONCAT('Orden: ', COALESCE(CAST(s.numeroOrden AS VARCHAR(50)),''), 
                       ' | RPU: ', COALESCE(s.rpuUsuario,''), 
                       ' | Operador: ', COALESCE(CAST(s.idOperador AS VARCHAR(20)),''),
                       ' | Comentarios: ', COALESCE(s.comentarios,'')
                ) AS detalle
              FROM dbo.Salidas s
              INNER JOIN dbo.Productos p ON p.idCodigo = s.idCodigo
              WHERE s.idHerramienta IS NULL
                AND s.fechaSalida >= ? AND s.fechaSalida < ?";
    $paramsSal = $paramsCommon;
    if (!empty($idCodigo)) { $sqlSal .= " AND s.idCodigo = ?"; $paramsSal[] = $idCodigo; }

    // SALIDA (SIN ORDEN) sin herramienta
    $sqlSso = "SELECT 
                so.fecha AS fecha,
                CAST('SALIDA (SIN ORDEN)' AS VARCHAR(40)) AS tipoMovimiento,
                p.codigo AS sku,
                p.descripcion,
                so.cantidad,
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
    if (!empty($idCodigo)) { $sqlSso .= " AND so.idCodigo = ?"; $paramsSso[] = $idCodigo; }

    // HERRAMIENTA PRESTADA (salida con idHerramienta)
    $sqlHP = "SELECT 
                s.fechaSalida AS fecha,
                CAST('HERRAMIENTA PRESTADA' AS VARCHAR(40)) AS tipoMovimiento,
                p.codigo AS sku,
                p.descripcion,
                s.cantidad,
                s.idCodigo,
                s.idHerramienta,
                hu.identificadorUnico,
                CONCAT('Orden: ', COALESCE(CAST(s.numeroOrden AS VARCHAR(50)),''), 
                       ' | RPU: ', COALESCE(s.rpuUsuario,''), 
                       ' | Operador: ', COALESCE(CAST(s.idOperador AS VARCHAR(20)),''),
                       ' | Comentarios: ', COALESCE(s.comentarios,'')
                ) AS detalle
              FROM dbo.Salidas s
              INNER JOIN dbo.Productos p ON p.idCodigo = s.idCodigo
              INNER JOIN dbo.HerramientasUnicas hu ON hu.idHerramienta = s.idHerramienta
              WHERE s.idHerramienta IS NOT NULL
                AND s.fechaSalida >= ? AND s.fechaSalida < ?";
    $paramsHP = $paramsCommon;
    if (!empty($idCodigo)) { $sqlHP .= " AND s.idCodigo = ?"; $paramsHP[] = $idCodigo; }

    // HERRAMIENTA DEVUELTA
    $sqlHD = "SELECT 
                d.fechaRetorno AS fecha,
                CAST('HERRAMIENTA DEVUELTA' AS VARCHAR(40)) AS tipoMovimiento,
                p.codigo AS sku,
                p.descripcion,
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
    if (!empty($idCodigo)) { $sqlHD .= " AND hu.idCodigo = ?"; $paramsHD[] = $idCodigo; }

    $rows = [];
    foreach ([[$sqlEnt,$paramsEnt],[$sqlSal,$paramsSal],[$sqlSso,$paramsSso],[$sqlHP,$paramsHP],[$sqlHD,$paramsHD]] as $pair) {
        [$sql,$params] = $pair;
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) { error_log("Query err: " . print_r(sqlsrv_errors(), true)); continue; }
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if ($r['fecha'] instanceof DateTime) $r['fecha'] = $r['fecha']->format('Y-m-d H:i:s');
            $rows[] = $r;
        }
        sqlsrv_free_stmt($stmt);
    }

    usort($rows, fn($a,$b) => strcmp($a['fecha'], $b['fecha']));
    return $rows;
}
