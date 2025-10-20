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

$ubicacion   = "Almacen";
$fechaParam  = date('Y-m-d H:i:s', strtotime($fecha));

error_log("Processing entry - Product: $idCodigo, Provider: $idProveedor, Quantity: $cantidad");

try {
    // CRITICAL FIX: Get a valid idCaja from CajaRegistro table
    $sqlCaja = "SELECT TOP 1 idCaja FROM CajaRegistro ORDER BY idCaja ASC";
    $stmtCaja = sqlsrv_query($conn, $sqlCaja);
    
    if ($stmtCaja === false) {
        error_log("Error getting idCaja: " . print_r(sqlsrv_errors(), true));
        throw new Exception("Error al obtener caja de registro");
    }
    
    $rowCaja = sqlsrv_fetch_array($stmtCaja, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtCaja);
    
    if (!$rowCaja) {
        // No CajaRegistro exists, try to create one or use a default
        error_log("No CajaRegistro found, attempting to create default");
        
        // Try to insert a default CajaRegistro record
        $sqlInsertCaja = "INSERT INTO CajaRegistro (nombreCaja, ubicacion) VALUES ('Caja Principal', 'Almacen')";
        $stmtInsertCaja = sqlsrv_query($conn, $sqlInsertCaja);
        
        if ($stmtInsertCaja === false) {
            error_log("Could not create CajaRegistro: " . print_r(sqlsrv_errors(), true));
            throw new Exception("No existe ninguna caja de registro en el sistema. Por favor, contacte al administrador.");
        }
        
        sqlsrv_free_stmt($stmtInsertCaja);
        
        // Get the newly created idCaja
        $stmtCaja2 = sqlsrv_query($conn, $sqlCaja);
        $rowCaja = sqlsrv_fetch_array($stmtCaja2, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtCaja2);
    }
    
    $idCaja = (int)$rowCaja['idCaja'];
    error_log("Using idCaja: $idCaja");

    // Get product information
    $sqlInfo = "SELECT codigo, tipo, descripcion FROM Productos WHERE idCodigo = ?";
    $stmtInfo = sqlsrv_query($conn, $sqlInfo, [$idCodigo]);
    
    if ($stmtInfo === false) {
        error_log("Error getting product info: " . print_r(sqlsrv_errors(), true));
        throw new Exception("Error al obtener información del producto");
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
        throw new Exception("Error al iniciar transacción");
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
        throw new Exception("Error al insertar entrada: " . $errors[0]['message']);
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
            throw new Exception("Error al contar herramientas");
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
                throw new Exception("Error al insertar herramienta única");
            }
            
            sqlsrv_free_stmt($stmtHerramienta);
            error_log("Tool created: $identificadorUnico");
        }
    }

    // 3) Update Inventory using MERGE (atomic upsert)
    error_log("Updating inventory for product $idCodigo with idCaja $idCaja");
    
    // Use MERGE for atomic upsert - matching your exact table structure
    $sqlMerge = "
        MERGE INTO Inventario AS target
        USING (SELECT ? AS idCodigo, ? AS idCaja) AS source
        ON target.idCodigo = source.idCodigo
        WHEN MATCHED THEN
            UPDATE SET 
                cantidadActual = target.cantidadActual + ?,
                ultimaActualizacion = GETDATE()
        WHEN NOT MATCHED THEN
            INSERT (idCodigo, idCaja, cantidadActual, ubicacion, ultimaActualizacion)
            VALUES (?, ?, ?, ?, GETDATE());
    ";
    
    $paramsMerge = [
        $idCodigo,      // source idCodigo
        $idCaja,        // source idCaja
        $cantidad,      // quantity to add in UPDATE
        $idCodigo,      // insert idCodigo
        $idCaja,        // insert idCaja  
        $cantidad,      // insert cantidadActual
        $ubicacion      // insert ubicacion
    ];
    
    error_log("Executing MERGE for inventory");
    error_log("MERGE params: " . print_r($paramsMerge, true));
    
    $stmtMerge = sqlsrv_query($conn, $sqlMerge, $paramsMerge);
    
    if ($stmtMerge === false) {
        $errors = sqlsrv_errors();
        error_log("Error with MERGE: " . print_r($errors, true));
        sqlsrv_rollback($conn);
        
        $errorMsg = "Error al actualizar inventario:\n";
        $errorMsg .= "SQLSTATE: " . $errors[0]['SQLSTATE'] . "\n";
        $errorMsg .= "Message: " . $errors[0]['message'];
        throw new Exception($errorMsg);
    }
    
    sqlsrv_free_stmt($stmtMerge);
    error_log("Inventory updated successfully");

    // Commit transaction
    if (!sqlsrv_commit($conn)) {
        error_log("Error committing transaction: " . print_r(sqlsrv_errors(), true));
        throw new Exception("Error al confirmar transacción");
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
    echo "<!DOCTYPE html>";
    echo "<html><head><meta charset='UTF-8'><title>Error</title>";
    echo "<style>body{font-family:Arial;padding:20px;} .error{background:#fee;border:1px solid #c00;padding:15px;border-radius:5px;}</style>";
    echo "</head><body>";
    echo "<div class='error'>";
    echo "<h2>Error al registrar la entrada</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    echo "<p><a href='../exstentry.php'>← Volver al formulario</a></p>";
    echo "</body></html>";
    exit();
}
?>