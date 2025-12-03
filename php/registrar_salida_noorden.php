<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    header("Location: ../exitnoord.php"); 
    exit(); 
}

// Database connection
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    error_log("Connection failed: " . print_r(sqlsrv_errors(), true));
    die("Error de conexión");
}

$area        = trim($_POST['areaSolicitante'] ?? '');
$encargado   = trim($_POST['encargadoArea'] ?? '');
$comentarios = trim($_POST['comentarios'] ?? '');
$fecha       = date('Y-m-d H:i:s');
$elementos   = $_POST['elementos'] ?? [];

error_log("Exit without order - Area: $area, Manager: $encargado");
error_log("Elements: " . print_r($elementos, true));

// Validate input
if ($area === '' || $encargado === '' || $comentarios === '' || empty($elementos)) {
    error_log("Validation failed - redirecting back");
    header("Location: ../exitnoord.php"); 
    exit();
}

// CRITICAL FIX: Check if ANY element requires tool selection BEFORE processing
$requiereSeleccionHerramienta = false;
$productosConHerramientas = [];

foreach ($elementos as $idx => $item) {
    $idCodigo = (int)($item['idCodigo'] ?? 0);
    $cantidad = (int)($item['cantidad'] ?? 0);
    
    if ($idCodigo === 0 || $cantidad <= 0) {
        continue;
    }

    // Check if this product has unique tools in inventory
    $sqlCheckTools = "SELECT COUNT(*) AS total 
                      FROM HerramientasUnicas 
                      WHERE idCodigo = ? AND enInventario = 1";
    $stmtCheck = sqlsrv_query($conn, $sqlCheckTools, [$idCodigo]);
    
    if ($stmtCheck) {
        $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        $toolsAvailable = (int)($rowCheck['total'] ?? 0);
        sqlsrv_free_stmt($stmtCheck);
        
        error_log("Product $idCodigo - Tools available: $toolsAvailable, Quantity needed: $cantidad");
        
        if ($toolsAvailable > 0) {
            // This product has tools, check if we have enough
            if ($toolsAvailable >= $cantidad) {
                $requiereSeleccionHerramienta = true;
                $productosConHerramientas[] = $idCodigo;
                error_log("Product $idCodigo requires tool selection");
            } else {
                // Not enough tools available
                error_log("Product $idCodigo - Insufficient tools: has $toolsAvailable, needs $cantidad");
                sqlsrv_close($conn);
                
                // Store error in session
                $_SESSION['exit_error'] = "Stock insuficiente de herramientas para el producto código: $idCodigo. Disponibles: $toolsAvailable, Requeridas: $cantidad";
                header("Location: ../exitnoord.php");
                exit();
            }
        }
    }
}

// CRITICAL FIX: If any product requires tool selection, redirect to tool selection page
if ($requiereSeleccionHerramienta) {
    error_log("Redirecting to tool selection page - products: " . implode(', ', $productosConHerramientas));
    
    // Store ALL form data in session for the tool selection page
    $_SESSION['salida_temporal'] = [
        'areaSolicitante' => $area,
        'encargadoArea' => $encargado,
        'comentarios' => $comentarios,
        'fecha' => $_POST['fecha'] ?? date('Y-m-d'),
        'elementos' => $elementos,
        'productos_con_herramientas' => $productosConHerramientas
    ];
    
    sqlsrv_close($conn);
    header("Location: ../exitnoorder2.php");
    exit();
}

// If we reach here, no tools required - process normal exit
error_log("No tools required - processing normal exit");

if (!sqlsrv_begin_transaction($conn)) {
    error_log("Failed to start transaction");
    die("Error al iniciar transacción");
}

try {
    foreach ($elementos as $item) {
        $idCodigo = (int)($item['idCodigo'] ?? 0);
        $cantidad = (int)($item['cantidad'] ?? 0);
        
        if ($idCodigo === 0 || $cantidad <= 0) {
            error_log("Skipping invalid element: idCodigo=$idCodigo, cantidad=$cantidad");
            continue;
        }

        error_log("Processing element: idCodigo=$idCodigo, cantidad=$cantidad");

        // Check total stock
        $stmt = sqlsrv_query($conn, "SELECT SUM(cantidadActual) AS stock FROM Inventario WHERE idCodigo = ?", [$idCodigo]);
        if (!$stmt) {
            throw new Exception('Error al verificar stock');
        }
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        $stock = (float)($row['stock'] ?? 0);
        
        error_log("Product $idCodigo - Stock available: $stock, Quantity needed: $cantidad");
        
        if ($stock < $cantidad) {
            throw new Exception("Stock insuficiente para el producto código: $idCodigo. Disponible: $stock, Requerido: $cantidad");
        }

        // Insert exit without order
        $sqlInsert = "INSERT INTO SalidaSinorden (areaSolicitante, encargadoArea, fecha, comentarios, idCodigo, cantidad)
                      VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = sqlsrv_query($conn, $sqlInsert, [$area, $encargado, $fecha, $comentarios, $idCodigo, $cantidad]);
        
        if (!$stmt) {
            $errors = sqlsrv_errors();
            error_log("Error inserting exit: " . print_r($errors, true));
            throw new Exception('Error al registrar salida');
        }
        sqlsrv_free_stmt($stmt);
        
        error_log("Exit registered successfully");

        // Update inventory (FIFO)
        $porDescontar = $cantidad;
        $stmt = sqlsrv_query(
            $conn,
            "SELECT idInventario, cantidadActual
             FROM Inventario
             WHERE idCodigo = ? AND cantidadActual > 0
             ORDER BY ultimaActualizacion ASC, idInventario ASC",
            [$idCodigo]
        );
        
        if (!$stmt) {
            throw new Exception('Error al leer inventario');
        }

        $filas = [];
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $filas[] = $r;
        }
        sqlsrv_free_stmt($stmt);

        error_log("Found " . count($filas) . " inventory rows for product $idCodigo");

        foreach ($filas as $f) {
            if ($porDescontar <= 0) break;
            
            $usar = min($porDescontar, (float)$f['cantidadActual']);
            error_log("Deducting $usar from inventory row " . $f['idInventario']);
            
            $ok = sqlsrv_query(
                $conn,
                "UPDATE Inventario
                 SET cantidadActual = cantidadActual - ?, ultimaActualizacion = ?
                 WHERE idInventario = ?",
                [$usar, $fecha, (int)$f['idInventario']]
            );
            
            if (!$ok) {
                $errors = sqlsrv_errors();
                error_log("Error updating inventory: " . print_r($errors, true));
                throw new Exception('Error al actualizar inventario');
            }
            
            $porDescontar -= $usar;
        }
        
        if ($porDescontar > 0) {
            error_log("Inventory mismatch: still need to deduct $porDescontar");
            throw new Exception('Error en el descuento de inventario');
        }
        
        error_log("Inventory updated successfully for product $idCodigo");
    }

    if (!sqlsrv_commit($conn)) {
        throw new Exception('Error al confirmar transacción');
    }
    
    error_log("Transaction committed successfully");
    sqlsrv_close($conn);
    header("Location: ../exitnoordcnf.php"); 
    exit();

} catch (Throwable $e) {
    error_log("Exception caught: " . $e->getMessage());
    sqlsrv_rollback($conn);
    sqlsrv_close($conn);
    
    // Store error message in session
    $_SESSION['exit_error'] = $e->getMessage();
    header("Location: ../exitnoord.php");
    exit();
}
?>