<?php
session_start();

// MAXIMUM DEBUG MODE
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Create a debug output function
function debug_output($message, $data = null) {
    $output = "[" . date('Y-m-d H:i:s') . "] " . $message;
    if ($data !== null) {
        $output .= ": " . print_r($data, true);
    }
    error_log($output);
    
    // Also echo for immediate viewing (remove in production)
    echo "<pre style='background:#f0f0f0; padding:10px; margin:5px; border:1px solid #ccc;'>";
    echo htmlspecialchars($output);
    echo "</pre>";
    flush();
}

debug_output("=== EXIT WITH ORDER PROCESS STARTED ===");

if (!isset($_SESSION['user_id'])) { 
    debug_output("ERROR: User not authenticated");
    header("Location: ../index.php"); 
    exit(); 
}

debug_output("User authenticated", $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    debug_output("ERROR: Not a POST request");
    header("Location: ../exitord.php"); 
    exit(); 
}

debug_output("POST request received", $_POST);

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
if (!$conn) {
    $errors = sqlsrv_errors();
    debug_output("ERROR: Database connection failed", $errors);
    die("Connection failed");
}

debug_output("Database connected successfully");

$rpu         = $_POST['rpuUsuario'] ?? '';
$orden       = $_POST['numeroOrden'] ?? '';
$comentarios = $_POST['comentarios'] ?? '';
$idOperador  = (int)($_POST['idOperador'] ?? 0);
$fecha       = date('Y-m-d H:i:s');
$elementos   = $_POST['elementos'] ?? [];

debug_output("Form data", [
    'rpu' => $rpu,
    'orden' => $orden,
    'idOperador' => $idOperador,
    'elementos_count' => count($elementos)
]);

// Validate input
if (strlen($rpu) !== 12 || !ctype_digit($rpu)) {
    debug_output("ERROR: Invalid RPU - length: " . strlen($rpu) . ", is_digit: " . (ctype_digit($rpu) ? 'yes' : 'no'));
    header("Location: ../exitord.php"); 
    exit();
}

if ($orden === '' || $idOperador === 0 || empty($elementos)) {
    debug_output("ERROR: Missing required fields", [
        'orden_empty' => ($orden === ''),
        'operator_zero' => ($idOperador === 0),
        'elements_empty' => empty($elementos)
    ]);
    header("Location: ../exitord.php"); 
    exit();
}

debug_output("✓ Validation passed");

// CHECK FOR TOOLS
debug_output("=== CHECKING FOR TOOLS ===");

$requiereSeleccionHerramienta = false;
$productosConHerramientas = [];

foreach ($elementos as $idx => $el) {
    $idCodigo = (int)($el['idCodigo'] ?? 0);
    $cantidad = (int)($el['cantidad'] ?? 0);
    
    debug_output("Checking element #$idx", ['idCodigo' => $idCodigo, 'cantidad' => $cantidad]);
    
    if ($idCodigo === 0 || $cantidad <= 0) {
        debug_output("  → Skipping invalid element");
        continue;
    }

    // Get product info
    $sqlProd = "SELECT codigo, descripcion, tipo FROM Productos WHERE idCodigo = ?";
    $stmtProd = sqlsrv_query($conn, $sqlProd, [$idCodigo]);
    
    if ($stmtProd && ($rowProd = sqlsrv_fetch_array($stmtProd, SQLSRV_FETCH_ASSOC))) {
        debug_output("  → Product found", $rowProd);
        sqlsrv_free_stmt($stmtProd);
    } else {
        debug_output("  → ERROR: Product not found in database");
        continue;
    }

    // Check if this product has unique tools in inventory
    $sqlCheckTools = "SELECT COUNT(*) AS total FROM HerramientasUnicas WHERE idCodigo = ? AND enInventario = 1";
    debug_output("  → Running tool check query", ['idCodigo' => $idCodigo]);
    
    $stmtCheck = sqlsrv_query($conn, $sqlCheckTools, [$idCodigo]);
    
    if ($stmtCheck) {
        $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        $toolsAvailable = (int)($rowCheck['total'] ?? 0);
        sqlsrv_free_stmt($stmtCheck);
        
        debug_output("  → Tools in inventory: $toolsAvailable (needed: $cantidad)");
        
        if ($toolsAvailable > 0) {
            debug_output("  → This product HAS tools available");
            
            if ($toolsAvailable >= $cantidad) {
                $requiereSeleccionHerramienta = true;
                $productosConHerramientas[] = $idCodigo;
                debug_output("  → ✓ ENOUGH TOOLS - Will require tool selection", [
                    'available' => $toolsAvailable,
                    'needed' => $cantidad
                ]);
            } else {
                debug_output("  → ✗ INSUFFICIENT TOOLS", [
                    'available' => $toolsAvailable,
                    'needed' => $cantidad
                ]);
                
                sqlsrv_close($conn);
                $_SESSION['exit_error'] = "Stock insuficiente de herramientas para el producto código: $idCodigo. Disponibles: $toolsAvailable, Requeridas: $cantidad";
                header("Location: ../exitord.php");
                exit();
            }
        } else {
            debug_output("  → No tools found (count=0) - will process as regular product");
        }
    } else {
        $errors = sqlsrv_errors();
        debug_output("  → ERROR: Failed to check tools", $errors);
    }
}

// DECISION POINT
debug_output("=== DECISION POINT ===");
debug_output("Requires tool selection", $requiereSeleccionHerramienta ? 'YES' : 'NO');

if ($requiereSeleccionHerramienta) {
    debug_output("Products requiring tool selection", $productosConHerramientas);
    
    // Store ALL form data in session
    $_SESSION['salida_temporal'] = [
        'rpuUsuario' => $rpu,
        'numeroOrden' => $orden,
        'comentarios' => $comentarios,
        'idOperador' => $idOperador,
        'fecha' => $_POST['fecha'] ?? date('Y-m-d'),
        'elementos' => $elementos,
        'productos_con_herramientas' => $productosConHerramientas
    ];
    
    debug_output("Session data stored", $_SESSION['salida_temporal']);
    debug_output("REDIRECTING to ../exitorderr2.php");
    
    sqlsrv_close($conn);
    
    // Give time to see the debug output
    echo "<hr><h2>Redirecting to tool selection page in 3 seconds...</h2>";
    echo "<p><a href='../exitorderr2.php'>Click here if not redirected</a></p>";
    echo "<script>setTimeout(() => window.location.href='../exitorderr2.php', 3000);</script>";
    exit();
}

debug_output("=== PROCESSING NORMAL EXIT (NO TOOLS) ===");

// Continue with normal exit processing...
if (!sqlsrv_begin_transaction($conn)) {
    debug_output("ERROR: Failed to start transaction", sqlsrv_errors());
    die("Transaction error");
}

debug_output("Transaction started");

try {
    foreach ($elementos as $el) {
        $idCodigo = (int)($el['idCodigo'] ?? 0);
        $cantidad = (int)($el['cantidad'] ?? 0);
        
        if ($idCodigo === 0 || $cantidad <= 0) {
            debug_output("Skipping invalid element in processing");
            continue;
        }

        debug_output("Processing exit for product $idCodigo, quantity: $cantidad");

        // Insert exit
        $sqlInsert = "INSERT INTO Salidas (rpuUsuario, numeroOrden, comentarios, idOperador, idCodigo, cantidad, fechaSalida)
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $ok = sqlsrv_query($conn, $sqlInsert, [$rpu, $orden, $comentarios, $idOperador, $idCodigo, $cantidad, $fecha]);
        
        if (!$ok) {
            $errors = sqlsrv_errors();
            debug_output("ERROR: Failed to insert exit", $errors);
            throw new Exception('Error inserting exit');
        }
        
        debug_output("✓ Exit inserted");

        // Update inventory (simplified for debugging)
        $sqlUpdate = "UPDATE Inventario 
                      SET cantidadActual = cantidadActual - ?, 
                          ultimaActualizacion = GETDATE()
                      WHERE idCodigo = ? AND cantidadActual >= ?";
        
        $ok = sqlsrv_query($conn, $sqlUpdate, [$cantidad, $idCodigo, $cantidad]);
        
        if (!$ok) {
            $errors = sqlsrv_errors();
            debug_output("ERROR: Failed to update inventory", $errors);
            throw new Exception('Error updating inventory');
        }
        
        $rowsAffected = sqlsrv_rows_affected($ok);
        debug_output("✓ Inventory updated", "Rows affected: $rowsAffected");
        
        if ($rowsAffected === 0) {
            throw new Exception("Insufficient stock for product $idCodigo");
        }
    }

    if (!sqlsrv_commit($conn)) {
        debug_output("ERROR: Failed to commit", sqlsrv_errors());
        throw new Exception('Commit failed');
    }
    
    debug_output("✓ Transaction committed successfully");
    debug_output("=== EXIT PROCESS COMPLETED SUCCESSFULLY ===");
    
    sqlsrv_close($conn);
    
    echo "<hr><h2>Success! Redirecting to confirmation page...</h2>";
    echo "<script>setTimeout(() => window.location.href='../exitordcnf.php', 2000);</script>";
    exit();

} catch (Throwable $e) {
    debug_output("EXCEPTION CAUGHT", $e->getMessage());
    sqlsrv_rollback($conn);
    sqlsrv_close($conn);
    
    $_SESSION['exit_error'] = $e->getMessage();
    
    echo "<hr><h2>Error occurred. Redirecting back to form...</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<script>setTimeout(() => window.location.href='../exitord.php', 3000);</script>";
    exit();
}
?>