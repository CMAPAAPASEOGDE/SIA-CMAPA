<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (!isset($_SESSION['user_id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    header("Location: ../exitord.php"); 
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

$rpu         = $_POST['rpuUsuario'] ?? '';
$orden       = $_POST['numeroOrden'] ?? '';
$comentarios = $_POST['comentarios'] ?? '';
$idOperador  = (int)($_POST['idOperador'] ?? 0);
$fecha       = date('Y-m-d H:i:s');
$elementos   = $_POST['elementos'] ?? [];

error_log("Exit with order - RPU: $rpu, Order: $orden, Elements: " . count($elementos));

// Validate input
if (strlen($rpu) !== 12 || !ctype_digit($rpu) || $orden === '' || $idOperador === 0 || empty($elementos)) {
    error_log("Validation failed");
    sqlsrv_close($conn);
    $_SESSION['exit_error'] = "Datos del formulario incompletos o inválidos";
    header("Location: ../exitord.php"); 
    exit();
}

// Start transaction
if (!sqlsrv_begin_transaction($conn)) {
    error_log("Failed to start transaction");
    sqlsrv_close($conn);
    die("Error al iniciar transacción");
}

try {
    foreach ($elementos as $el) {
        $idCodigo = (int)($el['idCodigo'] ?? 0);
        $cantidad = (int)($el['cantidad'] ?? 0);
        
        if ($idCodigo === 0 || $cantidad <= 0) {
            continue;
        }

        error_log("Processing product $idCodigo, quantity: $cantidad");

        // Check total stock
        $stmt = sqlsrv_query($conn, "SELECT SUM(cantidadActual) AS stock FROM Inventario WHERE idCodigo = ?", [$idCodigo]);
        if (!$stmt) {
            throw new Exception('Error verificando stock');
        }
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        $stock = (float)($row['stock'] ?? 0);
        
        if ($stock < $cantidad) {
            throw new Exception("Stock insuficiente para producto $idCodigo. Disponible: $stock, Necesario: $cantidad");
        }

        // Check if this product has unique tools
        $sqlCheckTools = "SELECT COUNT(*) AS total FROM HerramientasUnicas WHERE idCodigo = ? AND enInventario = 1";
        $stmtCheck = sqlsrv_query($conn, $sqlCheckTools, [$idCodigo]);
        
        $tieneHerramientas = false;
        if ($stmtCheck && ($rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC))) {
            $toolsDisponibles = (int)($rowCheck['total'] ?? 0);
            sqlsrv_free_stmt($stmtCheck);
            
            if ($toolsDisponibles > 0) {
                $tieneHerramientas = true;
                error_log("Product $idCodigo is a tool - Available: $toolsDisponibles, Needed: $cantidad");
                
                if ($toolsDisponibles < $cantidad) {
                    throw new Exception("Herramientas insuficientes para producto $idCodigo. Disponibles: $toolsDisponibles, Necesarias: $cantidad");
                }
            }
        }

        // Insert exit record
        $sqlInsert = "INSERT INTO Salidas (rpuUsuario, numeroOrden, comentarios, idOperador, idCodigo, cantidad, fechaSalida)
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmtInsert = sqlsrv_query($conn, $sqlInsert, [$rpu, $orden, $comentarios, $idOperador, $idCodigo, $cantidad, $fecha]);
        
        if (!$stmtInsert) {
            $errors = sqlsrv_errors();
            error_log("Error inserting exit: " . print_r($errors, true));
            throw new Exception('Error al registrar salida');
        }
        sqlsrv_free_stmt($stmtInsert);
        
        error_log("Exit registered successfully");

        // If this product has unique tools, automatically select and mark them
        if ($tieneHerramientas) {
            error_log("Automatically selecting $cantidad tools");
            
            // Get the first N available tools
            $sqlGetTools = "SELECT TOP (?) idHerramienta, identificadorUnico 
                            FROM HerramientasUnicas 
                            WHERE idCodigo = ? AND enInventario = 1 
                            ORDER BY idHerramienta ASC";
            $stmtTools = sqlsrv_query($conn, $sqlGetTools, [$cantidad, $idCodigo]);
            
            if (!$stmtTools) {
                throw new Exception("Error obteniendo herramientas disponibles");
            }
            
            $toolsToMark = [];
            while ($tool = sqlsrv_fetch_array($stmtTools, SQLSRV_FETCH_ASSOC)) {
                $toolsToMark[] = $tool;
            }
            sqlsrv_free_stmt($stmtTools);
            
            if (count($toolsToMark) < $cantidad) {
                throw new Exception("No hay suficientes herramientas disponibles");
            }
            
            // Mark each tool as out of inventory
            foreach ($toolsToMark as $tool) {
                $idHerramienta = (int)$tool['idHerramienta'];
                $identificador = $tool['identificadorUnico'];
                
                $sqlUpdateTool = "UPDATE HerramientasUnicas 
                                  SET enInventario = 0
                                  WHERE idHerramienta = ? AND enInventario = 1";
                $stmtUpdateTool = sqlsrv_query($conn, $sqlUpdateTool, [$idHerramienta]);
                
                if ($stmtUpdateTool === false) {
                    $errors = sqlsrv_errors();
                    error_log("Error updating tool $idHerramienta: " . print_r($errors, true));
                    throw new Exception("Error al actualizar herramienta ID $idHerramienta: " . $errors[0]['message']);
                }
                
                $rowsAffected = sqlsrv_rows_affected($stmtUpdateTool);
                sqlsrv_free_stmt($stmtUpdateTool);
                
                if ($rowsAffected === 0) {
                    error_log("Tool $idHerramienta was not updated (already out or doesn't exist)");
                    throw new Exception("La herramienta $identificador ya no está disponible");
                }
                
                error_log("Tool marked as out: $identificador (ID: $idHerramienta)");
            }
        }

        // Update inventory (FIFO - First In First Out)
        $porDescontar = $cantidad;
        $stmtInv = sqlsrv_query(
            $conn,
            "SELECT idInventario, cantidadActual
             FROM Inventario
             WHERE idCodigo = ? AND cantidadActual > 0
             ORDER BY ultimaActualizacion ASC, idInventario ASC",
            [$idCodigo]
        );
        
        if (!$stmtInv) {
            throw new Exception('Error leyendo inventario');
        }

        $filas = [];
        while ($r = sqlsrv_fetch_array($stmtInv, SQLSRV_FETCH_ASSOC)) {
            $filas[] = $r;
        }
        sqlsrv_free_stmt($stmtInv);

        foreach ($filas as $f) {
            if ($porDescontar <= 0) break;
            
            $usar = min($porDescontar, (float)$f['cantidadActual']);
            
            $sqlUpdateInv = "UPDATE Inventario
                             SET cantidadActual = cantidadActual - ?, 
                                 ultimaActualizacion = ?
                             WHERE idInventario = ?";
            $stmtUpdateInv = sqlsrv_query($conn, $sqlUpdateInv, [$usar, $fecha, (int)$f['idInventario']]);
            
            if (!$stmtUpdateInv) {
                error_log("Error updating inventory");
                throw new Exception('Error actualizando inventario');
            }
            sqlsrv_free_stmt($stmtUpdateInv);
            
            error_log("Deducted $usar from inventory row " . $f['idInventario']);
            $porDescontar -= $usar;
        }
        
        if ($porDescontar > 0) {
            error_log("Inventory mismatch: still need to deduct $porDescontar");
            throw new Exception('Error en el descuento de inventario');
        }
        
        error_log("Product $idCodigo processed successfully");
    }

    // Commit transaction
    if (!sqlsrv_commit($conn)) {
        throw new Exception('Error al confirmar transacción');
    }
    
    error_log("Exit completed successfully - redirecting to confirmation");
    sqlsrv_close($conn);
    
    // Clear any error messages
    unset($_SESSION['exit_error']);
    
    header("Location: ../exitordcnf.php"); 
    exit();

} catch (Throwable $e) {
    error_log("Error in exit process: " . $e->getMessage());
    sqlsrv_rollback($conn);
    sqlsrv_close($conn);
    
    $_SESSION['exit_error'] = $e->getMessage();
    header("Location: ../exitord.php");
    exit();
}
?>