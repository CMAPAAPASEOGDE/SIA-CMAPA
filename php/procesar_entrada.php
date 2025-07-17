<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Verificar el rol del usuario
$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1, 2])) {
    header("Location: ../acceso_denegado.php");
    exit();
}

// Recoger datos del formulario
$codigo = strtoupper(trim($_POST['codigo']));
$fecha = $_POST['fecha'];
$descripcion = trim($_POST['descripcion']);
$tipo = trim($_POST['tipo']);
$linea = trim($_POST['linea']);
$sublinea = trim($_POST['sublinea']);
$unidad = trim($_POST['unidad']);
$proveedor = (int)$_POST['proveedor'];
$cantidad = (int)$_POST['cantidad'];
$precio = floatval($_POST['precio']);
$stockMaximo = isset($_POST['stockMaximo']) ? (int)$_POST['stockMaximo'] : null;
$puntoReorden = isset($_POST['puntoReorden']) ? (int)$_POST['puntoReorden'] : null;

// Conectar a la base de datos
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = array(
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die("Error de conexión: " . print_r(sqlsrv_errors(), true));
}

try {
    // Iniciar transacción
    sqlsrv_begin_transaction($conn);
    
    // Paso 1: Verificar si el código ya existe
    $sqlCheck = "SELECT idCodigo FROM Productos WHERE codigo = ?";
    $paramsCheck = array($codigo);
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);
    
    if ($stmtCheck === false) {
        throw new Exception("Error al verificar código: " . print_r(sqlsrv_errors(), true));
    }
    
    if (sqlsrv_has_rows($stmtCheck)) {
        // Código ya existe, redirigir a página de error
        header("Location: ../nwentryer.php");
        exit();
    }
    
    // Paso 2: Insertar en la tabla Productos
    $sqlProducto = "INSERT INTO Productos (
                        codigo, 
                        descripcion, 
                        linea, 
                        sublinea, 
                        unidad, 
                        precio, 
                        puntoReorden, 
                        stockMaximo, 
                        tipo,
                        estatus
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
    
    $paramsProducto = array(
        $codigo,
        $descripcion,
        $linea,
        $sublinea,
        $unidad,
        $precio,
        $puntoReorden,
        $stockMaximo,
        $tipo
    );
    
    $stmtProducto = sqlsrv_query($conn, $sqlProducto, $paramsProducto);
    
    if ($stmtProducto === false) {
        throw new Exception("Error al insertar producto: " . print_r(sqlsrv_errors(), true));
    }
    
    // Obtener el ID del producto insertado
    $sqlLastId = "SELECT SCOPE_IDENTITY() AS idCodigo";
    $stmtLastId = sqlsrv_query($conn, $sqlLastId);
    
    if ($stmtLastId === false) {
        throw new Exception("Error al obtener ID del producto: " . print_r(sqlsrv_errors(), true));
    }
    
    $row = sqlsrv_fetch_array($stmtLastId, SQLSRV_FETCH_ASSOC);
    $idProducto = $row['idCodigo'];
    
    // Paso 3: Insertar en la tabla Entradas
    $sqlEntrada = "INSERT INTO Entradas (
                       fecha, 
                       cantidad, 
                       idProveedor, 
                       idCodigo
                   ) VALUES (?, ?, ?, ?)";
    
    $paramsEntrada = array(
        $fecha,
        $cantidad,
        $proveedor,
        $idProducto
    );
    
    $stmtEntrada = sqlsrv_query($conn, $sqlEntrada, $paramsEntrada);
    
    if ($stmtEntrada === false) {
        throw new Exception("Error al insertar entrada: " . print_r(sqlsrv_errors(), true));
    }
    
    // Paso 4: Insertar o actualizar en Inventario
    $sqlInventario = "MERGE INTO Inventario AS target
                      USING (VALUES (?, ?)) AS source (idCodigo, cantidad)
                      ON target.idCodigo = source.idCodigo
                      WHEN MATCHED THEN
                          UPDATE SET cantidadActual = target.cantidadActual + source.cantidad
                      WHEN NOT MATCHED THEN
                          INSERT (idCodigo, cantidadActual) VALUES (source.idCodigo, source.cantidad);";
    
    $paramsInventario = array($idProducto, $cantidad);
    $stmtInventario = sqlsrv_query($conn, $sqlInventario, $paramsInventario);
    
    if ($stmtInventario === false) {
        throw new Exception("Error al actualizar inventario: " . print_r(sqlsrv_errors(), true));
    }
    
    // Si todo está bien, confirmar transacción
    sqlsrv_commit($conn);
    
    // Redirigir a página de confirmación
    header("Location: ../nwentrycnf.php");
    exit();
    
} catch (Exception $e) {
    // Si hay error, revertir transacción
    sqlsrv_rollback($conn);
    
    // Registrar error (en producción, usar un sistema de logging)
    error_log($e->getMessage());
    
    // Redirigir a página de error
    header("Location: ../nwentryer.php");
    exit();
} finally {
    // Cerrar conexión
    if ($conn) {
        sqlsrv_close($conn);
    }
}
?>