<?php
// diagnostic_inventario.php - Upload this to your php/ folder and access it directly
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Not authenticated");
}

$serverName = "sqlserver-sia.database.windows.net";
$connOpts = [
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];

$conn = sqlsrv_connect($serverName, $connOpts);
if ($conn === false) die("Connection failed: " . print_r(sqlsrv_errors(), true));

echo "<h2>Inventario Table Structure Diagnostic</h2>";

// 1. Get table columns
echo "<h3>1. Inventario Table Columns:</h3>";
$sqlColumns = "SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE, 
    COLUMN_DEFAULT,
    CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'Inventario'
ORDER BY ORDINAL_POSITION";

$stmt = sqlsrv_query($conn, $sqlColumns);
if ($stmt) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Column</th><th>Type</th><th>Nullable</th><th>Default</th><th>Max Length</th></tr>";
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['COLUMN_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($row['DATA_TYPE']) . "</td>";
        echo "<td>" . htmlspecialchars($row['IS_NULLABLE']) . "</td>";
        echo "<td>" . htmlspecialchars($row['COLUMN_DEFAULT'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['CHARACTER_MAXIMUM_LENGTH'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    sqlsrv_free_stmt($stmt);
} else {
    echo "Error: " . print_r(sqlsrv_errors(), true);
}

// 2. Check constraints
echo "<h3>2. Check Constraints and Foreign Keys:</h3>";
$sqlConstraints = "SELECT 
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
WHERE TABLE_NAME = 'Inventario'";

$stmt = sqlsrv_query($conn, $sqlConstraints);
if ($stmt) {
    echo "<ul>";
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo "<li>" . htmlspecialchars($row['CONSTRAINT_NAME']) . " - " . htmlspecialchars($row['CONSTRAINT_TYPE']) . "</li>";
    }
    echo "</ul>";
    sqlsrv_free_stmt($stmt);
}

// 3. Test INSERT with a sample product
echo "<h3>3. Test INSERT Simulation:</h3>";
echo "<p>Let's test what happens when we try to insert:</p>";

$testIdCodigo = 1; // Change this to a valid idCodigo from your Productos table
$testCantidad = 10;
$testIdCaja = 1;
$testUbicacion = "Almacen";
$testFecha = date('Y-m-d H:i:s');

// Check if product exists
$sqlCheckProd = "SELECT TOP 1 idCodigo, codigo, descripcion FROM Productos WHERE idCodigo = ?";
$stmtCheck = sqlsrv_query($conn, $sqlCheckProd, [$testIdCodigo]);
$prodExists = false;
if ($stmtCheck && ($rowProd = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC))) {
    $prodExists = true;
    echo "<p><strong>Test Product Found:</strong> " . htmlspecialchars($rowProd['codigo']) . " - " . htmlspecialchars($rowProd['descripcion']) . "</p>";
}

if ($prodExists) {
    // Try the INSERT
    echo "<h4>Attempting INSERT:</h4>";
    echo "<pre>";
    $sqlInsert = "INSERT INTO Inventario (idCodigo, idCaja, cantidadActual, ubicacion, ultimaActualizacion)
                  VALUES (?, ?, ?, ?, ?)";
    echo "SQL: " . $sqlInsert . "\n";
    echo "Parameters:\n";
    echo "  idCodigo: $testIdCodigo\n";
    echo "  idCaja: $testIdCaja\n";
    echo "  cantidadActual: $testCantidad\n";
    echo "  ubicacion: $testUbicacion\n";
    echo "  ultimaActualizacion: $testFecha\n";
    echo "</pre>";
    
    // Actually try it (commented out to avoid inserting test data)
    // Uncomment to test:
    /*
    $stmtInsert = sqlsrv_query($conn, $sqlInsert, [$testIdCodigo, $testIdCaja, $testCantidad, $testUbicacion, $testFecha]);
    if ($stmtInsert === false) {
        echo "<p style='color:red;'><strong>INSERT FAILED!</strong></p>";
        echo "<pre>" . print_r(sqlsrv_errors(), true) . "</pre>";
    } else {
        echo "<p style='color:green;'><strong>INSERT SUCCEEDED!</strong></p>";
        sqlsrv_free_stmt($stmtInsert);
        
        // Clean up test data
        $sqlDelete = "DELETE FROM Inventario WHERE idCodigo = ? AND cantidadActual = ?";
        sqlsrv_query($conn, $sqlDelete, [$testIdCodigo, $testCantidad]);
    }
    */
    echo "<p><em>Note: Test INSERT is commented out. Uncomment in code to actually test.</em></p>";
}

// 4. Check for triggers
echo "<h3>4. Triggers on Inventario Table:</h3>";
$sqlTriggers = "SELECT name, is_disabled FROM sys.triggers WHERE parent_id = OBJECT_ID('Inventario')";
$stmt = sqlsrv_query($conn, $sqlTriggers);
if ($stmt) {
    $hasTriggers = false;
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $hasTriggers = true;
        echo "<p>Trigger: " . htmlspecialchars($row['name']) . " (Disabled: " . ($row['is_disabled'] ? 'Yes' : 'No') . ")</p>";
    }
    if (!$hasTriggers) {
        echo "<p>No triggers found.</p>";
    }
    sqlsrv_free_stmt($stmt);
}

sqlsrv_close($conn);
?>