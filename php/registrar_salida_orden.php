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
    header("Location: ../exitord.php"); 
    exit();
}

// ============================================================================
// STEP 1: CHECK IF ANY PRODUCT REQUIRES TOOL SELECTION (BEFORE TRANSACTION!)
// ============================================================================
$requiereHerramientas = false;
$datosHerramientas = [];

foreach ($elementos as $idx => $el) {
    $idCodigo = (int)($el['idCodigo'] ?? 0);
    $cantidad = (int)($el['cantidad'] ?? 0);
    
    if ($idCodigo === 0 || $cantidad <= 0) {
        continue;
    }

    // Check if this specific product has tools available
    $sql = "SELECT COUNT(*) AS total FROM HerramientasUnicas WHERE idCodigo = ? AND enInventario = 1";
    $stmt = sqlsrv_query($conn, $sql, [$idCodigo]);
    
    if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        $toolsDisponibles = (int)($row['total'] ?? 0);
        sqlsrv_free_stmt($stmt);
        
        error_log("Product $idCodigo - Tools available: $toolsDisponibles, Needed: $cantidad");
        
        if ($toolsDisponibles > 0) {
            // This product IS a tool with inventory tracking
            if ($toolsDisponibles >= $cantidad) {
                // Enough tools available - requires selection
                $requiereHerramientas = true;
                $datosHerramientas[$idCodigo] = [
                    'disponibles' => $toolsDisponibles,
                    'necesarias' => $cantidad
                ];
                error_log("Product $idCodigo REQUIRES tool selection");
            } else {
                // Not enough tools
                error_log("Product $idCodigo - INSUFFICIENT tools: $toolsDisponibles < $cantidad");
                sqlsrv_close($conn);
                $_SESSION['exit_error'] = "Herramientas insuficientes para el producto (ID: $idCodigo). Disponibles: $toolsDisponibles, Necesarias: $cantidad";
                header("Location: ../exitord.php");
                exit();
            }
        }
    }
}

// ============================================================================
// DECISION: Redirect to tool selection if needed
// ============================================================================
if ($requiereHerramientas) {
    error_log("REDIRECTING to tool selection - Products: " . implode(', ', array_keys($datosHerramientas)));
    
    // Store form data in session
    $_SESSION['salida_temporal'] = [
        'tipo' => 'orden',
        'rpuUsuario' => $rpu,
        'numeroOrden' => $orden,
        'comentarios' => $comentarios,
        'idOperador' => $idOperador,
        'fecha' => $_POST['fecha'] ?? date('Y-m-d'),
        'elementos' => $elementos,
        'herramientas_data' => $datosHerramientas
    ];
    
    sqlsrv_close($conn);
    header("Location: ../exitorderr2.php");
    exit();
}

// ============================================================================
// STEP 2: Process normal exit (no tools requiring selection)
// ============================================================================
error_log("Processing normal exit (no tool selection required)");

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

        // Check stock
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

        // Insert exit
        $sqlInsert = "INSERT INTO Salidas (rpuUsuario, numeroOrden, comentarios, idOperador, idCodigo, cantidad, fechaSalida)
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $ok = sqlsrv_query($conn, $sqlInsert, [$rpu, $orden, $comentarios, $idOperador, $idCodigo, $cantidad, $fecha]);
        
        if (!$ok) {
            throw new Exception('Error al registrar salida');
        }

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
            throw new Exception('Error leyendo inventario');
        }

        $filas = [];
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $filas[] = $r;
        }
        sqlsrv_free_stmt($stmt);

        foreach ($filas as $f) {
            if ($porDescontar <= 0) break;
            
            $usar = min($porDescontar, (float)$f['cantidadActual']);
            
            $ok = sqlsrv_query(
                $conn,
                "UPDATE Inventario
                 SET cantidadActual = cantidadActual - ?, ultimaActualizacion = ?
                 WHERE idInventario = ?",
                [$usar, $fecha, (int)$f['idInventario']]
            );
            
            if (!$ok) {
                throw new Exception('Error actualizando inventario');
            }
            
            $porDescontar -= $usar;
        }
        
        if ($porDescontar > 0) {
            throw new Exception('Error en descuento de inventario');
        }
    }

    if (!sqlsrv_commit($conn)) {
        throw new Exception('Error al confirmar transacción');
    }
    
    error_log("Exit completed successfully");
    sqlsrv_close($conn);
    header("Location: ../exitordcnf.php"); 
    exit();

} catch (Throwable $e) {
    error_log("Error: " . $e->getMessage());
    sqlsrv_rollback($conn);
    sqlsrv_close($conn);
    
    $_SESSION['exit_error'] = $e->getMessage();
    header("Location: ../exitord.php");
    exit();
}
?>