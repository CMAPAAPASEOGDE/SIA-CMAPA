<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Check authentication
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check role
$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1, 2])) {
    header("Location: ../acceso_denegado.php");
    exit();
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Error: Este archivo solo acepta solicitudes POST.');
}

error_log("Exit without order - POST data: " . print_r($_POST, true));

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
    die("Error de conexión a la base de datos");
}

try {
    // Validate required fields
    $areaSolicitante = trim($_POST['areaSolicitante'] ?? '');
    $encargadoArea = trim($_POST['encargadoArea'] ?? '');
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $comentarios = trim($_POST['comentarios'] ?? '');
    $elementos = $_POST['elementos'] ?? [];

    if (empty($areaSolicitante)) {
        throw new Exception("El área solicitante es requerida");
    }
    if (empty($encargadoArea)) {
        throw new Exception("Debe indicar quién solicita");
    }
    if (empty($elementos)) {
        throw new Exception("Debe agregar al menos un elemento");
    }

    $fechaParam = date('Y-m-d H:i:s', strtotime($fecha));

    // Check if any element is a tool (Herramienta)
    $tieneHerramientas = false;
    foreach ($elementos as $elem) {
        $idCodigo = isset($elem['idCodigo']) && is_numeric($elem['idCodigo']) ? (int)$elem['idCodigo'] : 0;
        if ($idCodigo > 0) {
            $sqlTipo = "SELECT tipo FROM Productos WHERE idCodigo = ?";
            $stmtTipo = sqlsrv_query($conn, $sqlTipo, [$idCodigo]);
            if ($stmtTipo) {
                $rowTipo = sqlsrv_fetch_array($stmtTipo, SQLSRV_FETCH_ASSOC);
                $tipo = strtolower(trim($rowTipo['tipo'] ?? ''));
                if (in_array($tipo, ['herramienta', 'herramientas'], true)) {
                    $tieneHerramientas = true;
                    break;
                }
                sqlsrv_free_stmt($stmtTipo);
            }
        }
    }

    // FIXED: Correct redirect paths
    if ($tieneHerramientas) {
        // Redirect to tool selection page with correct filename
        $_SESSION['salida_temporal'] = [
            'areaSolicitante' => $areaSolicitante,
            'encargadoArea' => $encargadoArea,
            'fecha' => $fecha,
            'comentarios' => $comentarios,
            'elementos' => $elementos
        ];
        sqlsrv_close($conn);
        header("Location: ../exitnoorder2.php"); // FIXED: Changed from exitnoord2.php
        exit();
    }

    // Process non-tool exit
    if (!sqlsrv_begin_transaction($conn)) {
        throw new Exception("Error al iniciar transacción");
    }

    foreach ($elementos as $elem) {
        $idCodigo = isset($elem['idCodigo']) && is_numeric($elem['idCodigo']) ? (int)$elem['idCodigo'] : 0;
        $cantidad = isset($elem['cantidad']) && is_numeric($elem['cantidad']) ? (int)$elem['cantidad'] : 0;

        if ($idCodigo <= 0 || $cantidad <= 0) {
            continue; // Skip invalid elements
        }

        // Insert into SalidaSinorden
        $sqlSalida = "INSERT INTO SalidaSinorden 
            (idCodigo, cantidad, areaSolicitante, encargadoArea, fecha, observaciones)
            VALUES (?, ?, ?, ?, ?, ?)";
        $paramsSalida = [$idCodigo, $cantidad, $areaSolicitante, $encargadoArea, $fechaParam, $comentarios];
        
        $stmtSalida = sqlsrv_query($conn, $sqlSalida, $paramsSalida);
        if ($stmtSalida === false) {
            $errors = sqlsrv_errors();
            error_log("Error inserting exit: " . print_r($errors, true));
            sqlsrv_rollback($conn);
            throw new Exception("Error al registrar salida: " . $errors[0]['message']);
        }
        sqlsrv_free_stmt($stmtSalida);

        // Update inventory
        $sqlUpdate = "UPDATE Inventario 
                      SET cantidadActual = cantidadActual - ?, 
                          ultimaActualizacion = GETDATE()
                      WHERE idCodigo = ?";
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [$cantidad, $idCodigo]);
        
        if ($stmtUpdate === false) {
            $errors = sqlsrv_errors();
            error_log("Error updating inventory: " . print_r($errors, true));
            sqlsrv_rollback($conn);
            throw new Exception("Error al actualizar inventario");
        }
        sqlsrv_free_stmt($stmtUpdate);
    }

    if (!sqlsrv_commit($conn)) {
        throw new Exception("Error al confirmar transacción");
    }

    sqlsrv_close($conn);
    header("Location: ../exitnoordcnf.php");
    exit();

} catch (Exception $e) {
    error_log("Exit without order error: " . $e->getMessage());
    
    if (isset($conn) && $conn) {
        sqlsrv_rollback($conn);
        sqlsrv_close($conn);
    }
    
    echo "<!DOCTYPE html>";
    echo "<html><head><meta charset='UTF-8'><title>Error</title>";
    echo "<style>body{font-family:Arial;padding:20px;} .error{background:#fee;border:1px solid #c00;padding:15px;border-radius:5px;}</style>";
    echo "</head><body>";
    echo "<div class='error'>";
    echo "<h2>Error al registrar la salida</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    echo "<p><a href='../exitnoord.php'>← Volver al formulario</a></p>";
    echo "</body></html>";
    exit();
}
?>