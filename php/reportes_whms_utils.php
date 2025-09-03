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
    $start = "$anio-$mes-01";
    $endNext = date('Y-m-d', strtotime("$start +1 month"));
    return [$start, $endNext];
}

function fetch_movimientos_almacen($conn, $mes, $anio, $idCodigo = null) {
    list($start, $endNext) = range_from_month_year($mes, $anio);
    
    // Base query for warehouse movements (similar to kardex but without prices)
    $sql = "
    SELECT 
        fecha,
        'Entrada' as tipoMovimiento,
        p.codigo as sku,
        p.descripcion,
        CAST(e.cantidad AS FLOAT) as cantidad,
        e.idHerramienta,
        e.identificadorUnico,
        CONCAT('Entrada - ', COALESCE(e.observaciones, '')) as detalle,
        p.idCodigo
    FROM dbo.Entradas e
    INNER JOIN dbo.Productos p ON e.idCodigo = p.idCodigo
    WHERE e.fecha >= ? AND e.fecha < ?
    " . ($idCodigo ? " AND e.idCodigo = ?" : "") . "
    
    UNION ALL
    
    SELECT 
        fechaSalida as fecha,
        'Salida' as tipoMovimiento,
        p.codigo as sku,
        p.descripcion,
        CAST(s.cantidad AS FLOAT) as cantidad,
        s.idHerramienta,
        s.identificadorUnico,
        CONCAT('Salida - ', COALESCE(s.observaciones, '')) as detalle,
        p.idCodigo
    FROM dbo.Salidas s
    INNER JOIN dbo.Productos p ON s.idCodigo = p.idCodigo
    WHERE s.fechaSalida >= ? AND s.fechaSalida < ?
    " . ($idCodigo ? " AND s.idCodigo = ?" : "") . "
    
    UNION ALL
    
    SELECT 
        fecha,
        'Salida Sin Orden' as tipoMovimiento,
        p.codigo as sku,
        p.descripcion,
        CAST(sso.cantidad AS FLOAT) as cantidad,
        sso.idHerramienta,
        sso.identificadorUnico,
        CONCAT('Salida Sin Orden - ', COALESCE(sso.observaciones, '')) as detalle,
        p.idCodigo
    FROM dbo.SalidaSinorden sso
    INNER JOIN dbo.Productos p ON sso.idCodigo = p.idCodigo
    WHERE sso.fecha >= ? AND sso.fecha < ?
    " . ($idCodigo ? " AND sso.idCodigo = ?" : "") . "
    
    ORDER BY fecha ASC, tipoMovimiento ASC
    ";
    
    // Prepare parameters
    $params = [$start, $endNext, $start, $endNext, $start, $endNext];
    if ($idCodigo) {
        // Insert idCodigo parameters at the right positions
        array_splice($params, 2, 0, [$idCodigo]);
        array_splice($params, 5, 0, [$idCodigo]);
        array_splice($params, 8, 0, [$idCodigo]);
    }
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("Query failed: " . print_r(sqlsrv_errors(), true));
        return [];
    }
    
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Format fecha if it's a DateTime object
        if (is_object($row['fecha']) && method_exists($row['fecha'], 'format')) {
            $row['fecha'] = $row['fecha']->format('Y-m-d H:i:s');
        }
        $rows[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    
    return $rows;
}

function insert_notification($conn, $descripcion, $idCodigo = null, $idOperario = null, $idRol = 1) {
    try {
        // Log the notification (simple logging approach)
        error_log("Warehouse Movement Report: " . $descripcion);
        
        // If you have a notifications table, you can uncomment and modify this:
        /*
        $sql = "INSERT INTO Notificaciones (descripcion, idCodigo, idOperario, idRol, fecha) 
                VALUES (?, ?, ?, ?, GETDATE())";
        $stmt = sqlsrv_query($conn, $sql, [$descripcion, $idCodigo, $idOperario, $idRol]);
        if ($stmt) {
            sqlsrv_free_stmt($stmt);
        }
        */
        
        return true;
    } catch (Exception $e) {
        error_log("Error inserting notification: " . $e->getMessage());
        return false;
    }
}
?>