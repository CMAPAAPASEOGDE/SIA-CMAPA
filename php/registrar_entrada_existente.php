<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Error: Este archivo solo acepta solicitudes POST. Por favor, use el formulario de entrada.');
}

// Log the POST data for debugging
error_log("POST data received: " . print_r($_POST, true));

// Database connection
$serverName = "sqlserver-sia.database.windows.net";
$connOpts = [
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];

$conn = sqlsrv_connect($serverName, $connOpts);
if ($conn === false) {
    error_log("Database connection failed: " . print_r(sqlsrv_errors(), true));
    die("Error de conexión a la base de datos: " . print_r(sqlsrv_errors(), true));
}

// Validate and sanitize input
$idCodigo    = isset($_POST['idCodigo']) && is_numeric($_POST['idCodigo']) ? (int)$_POST['idCodigo'] : 0;
$idProveedor = isset($_POST['idProveedor']) && is_numeric($_POST['idProveedor']) ? (int)$_POST['idProveedor'] : 0;
$cantidad    = isset($_POST['cantidad']) && is_numeric($_POST['cantidad']) ? (int)$_POST['cantidad'] : 0;
$fecha       = isset($_POST['fecha']) && !empty($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d');

// Validate required fields
if ($idCodigo <= 0) {
    die("Error: Debe seleccionar un código de producto válido.");
}
if ($idProveedor <= 0) {
    die("Error: Debe seleccionar un proveedor válido.");
}
if ($cantidad <= 0) {
    die("Error: La cantidad debe ser mayor a 0.");
}

$idCaja      = 1;
$ubicacion   = "Almacen";
$fechaParam  = date('Y-m-d H:i:s', strtotime($fecha));

error_log("Processing entry - Product: $idCodigo, Provider: $idProveedor, Quantity: $cantidad");

try {
    // 0) Get product information
    $sqlInfo = "SELECT codigo, tipo, descripcion FROM Productos WHERE idCodigo = ?";
    $stmtInfo = sqlsrv_query($conn, $sqlInfo, [$idCodigo]);
    
    if ($stmtInfo === false) {
        error_log("Error getting product info: " . print_r(sqlsrv_errors(), true));
        throw new Exception("Error al obtener información del producto: " . print_r(sqlsrv_errors(), true));
    }
    
    $rowInfo = sqlsrv_fetch_array($stmtInfo, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtInfo);
    
    if (!$rowInfo) {
        error_log("Product not found with idCodigo: $idCodigo");
        throw new Exception("Producto no encontrado con ID: $idCodigo");
    }

    $codigoProducto = $rowInfo['codigo'] ?? '';
    $tipo           = strtolower(trim($rowInfo['tipo'] ?? ''));
    $esHerramienta  = in_array($tipo, ['herramienta', 'herramientas'], true);

    error_log("Product found - Code: $codigoProducto, Type: $tipo, Is Tool: " . ($esHerramienta ? 'Yes' : 'No'));

    // Begin transaction
    if (!sqlsrv_begin_transaction($conn)) {
        error_log("Failed to start transaction: " . print_r(sqlsrv_errors(), true));
        throw new Exception("Error al iniciar transacción: " . print_r(sqlsrv_errors(), true));
    }

    // 1) Insert into Entradas
    $sqlEntrada = "INSERT INTO Entradas (idCodigo, idProveedor, cantidad, fecha) 
                   VALUES (?, ?, ?, ?)";
    $paramsEntrada = [$idCodigo, $idProveedor, $cantidad, $fechaParam];
    
    error_log("Inserting into Entradas");
    
    $stmtEntrada = sqlsrv_query($conn, $sqlEntrada, $paramsEntrada);
    
    if ($stmtEntrada === false) {
        $errors = sqlsrv_errors();
        error_log("Error inserting into Entradas: " . print_r($errors, true));
        sqlsrv_rollback($conn);
        throw new Exception("Error al insertar entrada: " . print_r($errors, true));
    }
    
    sqlsrv_free_stmt($stmtEntrada);
    error_log("Entry inserted successfully");

    // 2) Insert unique tools if applicable
    if ($esHerramienta && $cantidad > 0) {
        error_log("Processing as tool - creating unique instances");
        
        // Get current count of tools
        $sqlContador = "SELECT COUNT(*) AS total FROM HerramientasUnicas WHERE idCodigo = ?";
        $stmtContador = sqlsrv_query($conn, $sqlContador, [$idCodigo]);
        
        if ($stmtContador === false) {
            error_log("Error counting tools: " . print_r(sqlsrv_errors(), true));
            sqlsrv_rollback($conn);
            throw new Exception("Error al contar herramientas: " . print_r(sqlsrv_errors(), true));
        }
        
        $rowContador = sqlsrv_fetch_array($stmtContador, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtContador);
        $contador = (int)($rowContador['total'] ?? 0);

        error_log("Current tool count: $contador, creating $cantidad new tools");

        // Create individual tool instances
        for ($i = 1; $i <= $cantidad; $i++) {
            $identificadorUnico = $codigoProducto . '-' . ($contador + $i);

            $sqlHerramienta = "INSERT INTO HerramientasUnicas 
                (idCodigo, fechaEntrada, estadoActual, observaciones, enInventario, identificadorUnico)
                VALUES (?, ?, 'Funcional', 'Nueva herramienta', 1, ?)";
            $paramsHerramienta = [$idCodigo, $fechaParam, $identificadorUnico];
            
            $stmtHerramienta = sqlsrv_query($conn, $sqlHerramienta, $paramsHerramienta);
            
            if ($stmtHerramienta === false) {
                error_log("Error inserting tool $i: " . print_r(sqlsrv_errors(), true));
                sqlsrv_rollback($conn);
                throw new Exception("Error al insertar herramienta única: " . print_r(sqlsrv_errors(), true));
            }
            
            sqlsrv_free_stmt($stmtHerramienta);
            error_log("Tool created: $identificadorUnico");
        }
    }

    // 3) Update Inventory - CRITICAL FIX HERE
    error_log("Updating inventory for product $idCodigo");
    
    // First, check if record exists
    $sqlCheck = "SELECT cantidadActual FROM Inventario WHERE idCodigo = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$idCodigo]);
    
    if ($stmtCheck === false) {
        error_log("Error checking inventory: " . print_r(sqlsrv_errors(), true));
        sqlsrv_rollback($conn);
        throw new Exception("Error al verificar inventario: " . print_r(sqlsrv_errors(), true));
    }

    $rowInv = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtCheck);
    
    if ($rowInv) {
        // Update existing inventory
        $cantidadActual = (int)($rowInv['cantidadActual'] ?? 0);
        $nuevaCantidad = $cantidadActual + $cantidad;
        
        error_log("Updating existing inventory from $cantidadActual to $nuevaCantidad");
        
        $sqlUpdate = "UPDATE Inventario 
                      SET cantidadActual = ?, ultimaActualizacion = ? 
                      WHERE idCodigo = ?";
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [$nuevaCantidad, $fechaParam, $idCodigo]);
        
        if ($stmtUpdate === false) {
            error_log("Error updating inventory: " . print_r(sqlsrv_errors(), true));
            sqlsrv_rollback($conn);
            throw new Exception("Error al actualizar inventario: " . print_r($errors, true));
        }
        
        sqlsrv_free_stmt($stmtUpdate);
        error_log("Inventory updated successfully");
        
    } else {
        // Insert new inventory record - FIX: Get all required columns
        error_log("Creating new inventory record with quantity $cantidad");
        
        // Check what columns exist in Inventario table
        // Common variations include: idInventario, idProducto, etc.
        
        // Try MERGE as alternative (SQL Server upsert)
        $sqlMerge = "
            MERGE INTO Inventario AS target
            USING (SELECT ? AS idCodigo) AS source
            ON target.idCodigo = source.idCodigo
            WHEN MATCHED THEN
                UPDATE SET 
                    cantidadActual = target.cantidadActual + ?,
                    ultimaActualizacion = ?
            WHEN NOT MATCHED THEN
                INSERT (idCodigo, idCaja, cantidadActual, ubicacion, ultimaActualizacion)
                VALUES (?, ?, ?, ?, ?);
        ";
        
        $paramsMerge = [
            $idCodigo,              // source idCodigo
            $cantidad,              // quantity to add
            $fechaParam,            // update date
            $idCodigo,              // insert idCodigo
            $idCaja,                // insert idCaja
            $cantidad,              // insert quantity
            $ubicacion,             // insert location
            $fechaParam             // insert date
        ];
        
        error_log("Using MERGE statement for inventory upsert");
        error_log("MERGE params: " . print_r($paramsMerge, true));
        
        $stmtMerge = sqlsrv_query($conn, $sqlMerge, $paramsMerge);
        
        if ($stmtMerge === false) {
            $errors = sqlsrv_errors();
            error_log("Error with MERGE: " . print_r($errors, true));
            
            // If MERGE fails, try traditional INSERT with explicit column list
            error_log("MERGE failed, trying traditional INSERT");
            
            // Build INSERT with only required columns (adjust based on your table)
            $sqlInsert = "INSERT INTO Inventario (idCodigo, cantidadActual, ultimaActualizacion";
            $paramsInsert = [$idCodigo, $cantidad, $fechaParam];
            
            // Add optional columns if they exist
            if ($idCaja !== null) {
                $sqlInsert .= ", idCaja";
                $paramsInsert[] = $idCaja;
            }
            if ($ubicacion !== null) {
                $sqlInsert .= ", ubicacion";
                $paramsInsert[] = $ubicacion;
            }
            
            $sqlInsert .= ") VALUES (" . implode(", ", array_fill(0, count($paramsInsert), "?")) . ")";
            
            error_log("INSERT SQL: $sqlInsert");
            error_log("INSERT params: " . print_r($paramsInsert, true));
            
            $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);
            
            if ($stmtInsert === false) {
                $insertErrors = sqlsrv_errors();
                error_log("Error inserting inventory: " . print_r($insertErrors, true));
                sqlsrv_rollback($conn);
                
                // Provide detailed error message
                $errorMsg = "Error al insertar en inventario. Detalles:\n";
                foreach ($insertErrors as $error) {
                    $errorMsg .= "SQLSTATE: " . $error['SQLSTATE'] . "\n";
                    $errorMsg .= "Code: " . $error['code'] . "\n";
                    $errorMsg .= "Message: " . $error['message'] . "\n";
                }
                throw new Exception($errorMsg);
            }
            
            sqlsrv_free_stmt($stmtInsert);
        } else {
            sqlsrv_free_stmt($stmtMerge);
        }
        
        error_log("New inventory record created successfully");
    }

    // Commit transaction
    if (!sqlsrv_commit($conn)) {
        error_log("Error committing transaction: " . print_r(sqlsrv_errors(), true));
        throw new Exception("Error al confirmar transacción: " . print_r(sqlsrv_errors(), true));
    }

    error_log("Entry registered successfully - redirecting to confirmation page");
    
    sqlsrv_close($conn);
    header("Location: ../extetcnf.php");
    exit();

} catch (Exception $e) {
    error_log("Exception caught: " . $e->getMessage());
    
    if (isset($conn) && $conn) {
        sqlsrv_rollback($conn);
        sqlsrv_close($conn);
    }
    
    // Display user-friendly error
    echo "<html><head><meta charset='UTF-8'></head><body>";
    echo "<h2>Error al registrar la entrada</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p><a href='../exstentry.php'>Volver al formulario</a></p>";
    echo "</body></html>";
    exit();
}
?>