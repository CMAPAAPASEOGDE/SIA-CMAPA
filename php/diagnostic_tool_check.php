<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Not authenticated");
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
if (!$conn) {
    die("Connection failed: " . print_r(sqlsrv_errors(), true));
}

// Get a sample product ID from the form if provided
$testIdCodigo = isset($_GET['idCodigo']) ? (int)$_GET['idCodigo'] : null;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Tool Inventory Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .info { background: #e7f3fe; border-left: 6px solid #2196F3; padding: 10px; margin: 10px 0; }
        .warning { background: #fff3cd; border-left: 6px solid #ffc107; padding: 10px; margin: 10px 0; }
        .error { background: #f8d7da; border-left: 6px solid #dc3545; padding: 10px; margin: 10px 0; }
        .success { background: #d4edda; border-left: 6px solid #28a745; padding: 10px; margin: 10px 0; }
        form { background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🔧 Tool Inventory Diagnostic</h1>
    
    <form method="GET">
        <label>Enter Product ID (idCodigo) to check:</label>
        <input type="number" name="idCodigo" value="<?= $testIdCodigo ?? '' ?>" required>
        <button type="submit">Check Product</button>
    </form>

    <?php if ($testIdCodigo): ?>
        
        <h2>Checking Product ID: <?= $testIdCodigo ?></h2>
        
        <!-- 1. Product Information -->
        <h3>1. Product Information</h3>
        <?php
        $sqlProd = "SELECT idCodigo, codigo, descripcion, tipo FROM Productos WHERE idCodigo = ?";
        $stmtProd = sqlsrv_query($conn, $sqlProd, [$testIdCodigo]);
        
        if ($stmtProd && ($rowProd = sqlsrv_fetch_array($stmtProd, SQLSRV_FETCH_ASSOC))) {
            echo "<div class='success'>";
            echo "<strong>Product Found:</strong><br>";
            echo "ID: " . $rowProd['idCodigo'] . "<br>";
            echo "Code: " . htmlspecialchars($rowProd['codigo']) . "<br>";
            echo "Description: " . htmlspecialchars($rowProd['descripcion']) . "<br>";
            echo "Type: <strong>" . htmlspecialchars($rowProd['tipo']) . "</strong>";
            echo "</div>";
            
            $tipo = strtolower(trim($rowProd['tipo'] ?? ''));
            $esHerramienta = in_array($tipo, ['herramienta', 'herramientas'], true);
            
            if ($esHerramienta) {
                echo "<div class='info'><strong>✓ This is a TOOL (Herramienta)</strong></div>";
            } else {
                echo "<div class='warning'><strong>✗ This is NOT a tool. Type: " . htmlspecialchars($rowProd['tipo']) . "</strong></div>";
            }
        } else {
            echo "<div class='error'><strong>Product NOT found with ID: $testIdCodigo</strong></div>";
        }
        sqlsrv_free_stmt($stmtProd);
        ?>

        <!-- 2. Inventory Check -->
        <h3>2. General Inventory (Inventario table)</h3>
        <?php
        $sqlInv = "SELECT * FROM Inventario WHERE idCodigo = ?";
        $stmtInv = sqlsrv_query($conn, $sqlInv, [$testIdCodigo]);
        
        if ($stmtInv) {
            echo "<table>";
            echo "<tr><th>idInventario</th><th>idCodigo</th><th>idCaja</th><th>cantidadActual</th><th>ubicacion</th><th>ultimaActualizacion</th></tr>";
            
            $totalStock = 0;
            $hasInventory = false;
            
            while ($rowInv = sqlsrv_fetch_array($stmtInv, SQLSRV_FETCH_ASSOC)) {
                $hasInventory = true;
                $totalStock += (float)$rowInv['cantidadActual'];
                
                echo "<tr>";
                echo "<td>" . $rowInv['idInventario'] . "</td>";
                echo "<td>" . $rowInv['idCodigo'] . "</td>";
                echo "<td>" . $rowInv['idCaja'] . "</td>";
                echo "<td><strong>" . $rowInv['cantidadActual'] . "</strong></td>";
                echo "<td>" . htmlspecialchars($rowInv['ubicacion'] ?? '') . "</td>";
                $fecha = $rowInv['ultimaActualizacion'];
                echo "<td>" . (is_object($fecha) ? $fecha->format('Y-m-d H:i:s') : $fecha) . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            if ($hasInventory) {
                echo "<div class='success'><strong>Total Stock in Inventory: $totalStock units</strong></div>";
            } else {
                echo "<div class='error'><strong>No inventory records found for this product</strong></div>";
            }
            
            sqlsrv_free_stmt($stmtInv);
        }
        ?>

        <!-- 3. Tool-Specific Check (HerramientasUnicas) -->
        <h3>3. Unique Tools (HerramientasUnicas table)</h3>
        <?php
        $sqlTools = "SELECT * FROM HerramientasUnicas WHERE idCodigo = ? ORDER BY enInventario DESC, idHerramienta ASC";
        $stmtTools = sqlsrv_query($conn, $sqlTools, [$testIdCodigo]);
        
        if ($stmtTools) {
            echo "<table>";
            echo "<tr><th>idHerramienta</th><th>identificadorUnico</th><th>estadoActual</th><th>enInventario</th><th>fechaEntrada</th><th>observaciones</th></tr>";
            
            $totalTools = 0;
            $availableTools = 0;
            $hasTools = false;
            
            while ($rowTool = sqlsrv_fetch_array($stmtTools, SQLSRV_FETCH_ASSOC)) {
                $hasTools = true;
                $totalTools++;
                
                $enInventario = (int)$rowTool['enInventario'];
                if ($enInventario === 1) {
                    $availableTools++;
                }
                
                $rowClass = $enInventario === 1 ? 'style="background-color: #d4edda;"' : 'style="background-color: #f8d7da;"';
                
                echo "<tr $rowClass>";
                echo "<td>" . $rowTool['idHerramienta'] . "</td>";
                echo "<td><strong>" . htmlspecialchars($rowTool['identificadorUnico'] ?? '') . "</strong></td>";
                echo "<td>" . htmlspecialchars($rowTool['estadoActual'] ?? '') . "</td>";
                echo "<td><strong>" . ($enInventario === 1 ? 'YES ✓' : 'NO ✗') . "</strong></td>";
                $fecha = $rowTool['fechaEntrada'];
                echo "<td>" . (is_object($fecha) ? $fecha->format('Y-m-d H:i:s') : $fecha) . "</td>";
                echo "<td>" . htmlspecialchars($rowTool['observaciones'] ?? '') . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            if ($hasTools) {
                echo "<div class='info'>";
                echo "<strong>Total Tools Registered: $totalTools</strong><br>";
                echo "<strong>Available in Inventory (enInventario=1): $availableTools</strong><br>";
                echo "<strong>Out of Inventory (enInventario=0): " . ($totalTools - $availableTools) . "</strong>";
                echo "</div>";
                
                if ($availableTools > 0) {
                    echo "<div class='success'><strong>✓ This product HAS tools available for exit</strong></div>";
                } else {
                    echo "<div class='error'><strong>✗ All tools are currently out of inventory</strong></div>";
                }
            } else {
                echo "<div class='warning'><strong>No tools found in HerramientasUnicas table for this product</strong></div>";
            }
            
            sqlsrv_free_stmt($stmtTools);
        }
        ?>

        <!-- 4. The Exact Check Your Code Uses -->
        <h3>4. Tool Detection Query (What Your Code Actually Checks)</h3>
        <?php
        $sqlCheck = "SELECT COUNT(*) AS total FROM HerramientasUnicas WHERE idCodigo = ? AND enInventario = 1";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$testIdCodigo]);
        
        if ($stmtCheck && ($rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC))) {
            $count = (int)$rowCheck['total'];
            
            if ($count > 0) {
                echo "<div class='success'>";
                echo "<strong>✓ Query Result: $count tool(s) available</strong><br>";
                echo "Your code SHOULD redirect to tool selection page with this product.";
                echo "</div>";
            } else {
                echo "<div class='error'>";
                echo "<strong>✗ Query Result: 0 tools available</strong><br>";
                echo "Your code will NOT redirect to tool selection page.<br>";
                echo "Possible reasons:<br>";
                echo "1. No records in HerramientasUnicas for this product<br>";
                echo "2. All tools have enInventario = 0 (already out)<br>";
                echo "3. Wrong idCodigo being used";
                echo "</div>";
            }
            
            sqlsrv_free_stmt($stmtCheck);
        }
        ?>

        <!-- 5. Check Data Types -->
        <h3>5. Data Type Check</h3>
        <?php
        echo "<div class='info'>";
        echo "<strong>Checking enInventario field values:</strong><br>";
        
        $sqlTypes = "SELECT idHerramienta, enInventario, 
                     CASE WHEN enInventario = 1 THEN 'TRUE' WHEN enInventario = 0 THEN 'FALSE' ELSE 'OTHER' END AS result
                     FROM HerramientasUnicas WHERE idCodigo = ?";
        $stmtTypes = sqlsrv_query($conn, $sqlTypes, [$testIdCodigo]);
        
        if ($stmtTypes) {
            echo "<table>";
            echo "<tr><th>idHerramienta</th><th>enInventario (raw)</th><th>Comparison Result</th></tr>";
            
            while ($rowType = sqlsrv_fetch_array($stmtTypes, SQLSRV_FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . $rowType['idHerramienta'] . "</td>";
                echo "<td>" . var_export($rowType['enInventario'], true) . "</td>";
                echo "<td><strong>" . $rowType['result'] . "</strong></td>";
                echo "</tr>";
            }
            
            echo "</table>";
            sqlsrv_free_stmt($stmtTypes);
        }
        echo "</div>";
        ?>

    <?php else: ?>
        
        <div class='info'>
            <strong>Please enter a Product ID (idCodigo) above to check its tool inventory status.</strong>
        </div>

        <h3>All Products with Tools</h3>
        <?php
        $sqlAllTools = "SELECT DISTINCT p.idCodigo, p.codigo, p.descripcion, p.tipo,
                        COUNT(h.idHerramienta) as total_tools,
                        SUM(CASE WHEN h.enInventario = 1 THEN 1 ELSE 0 END) as available_tools
                        FROM Productos p
                        INNER JOIN HerramientasUnicas h ON p.idCodigo = h.idCodigo
                        GROUP BY p.idCodigo, p.codigo, p.descripcion, p.tipo
                        ORDER BY p.codigo";
        
        $stmtAllTools = sqlsrv_query($conn, $sqlAllTools);
        
        if ($stmtAllTools) {
            echo "<table>";
            echo "<tr><th>idCodigo</th><th>Code</th><th>Description</th><th>Type</th><th>Total Tools</th><th>Available</th><th>Action</th></tr>";
            
            $hasAny = false;
            while ($row = sqlsrv_fetch_array($stmtAllTools, SQLSRV_FETCH_ASSOC)) {
                $hasAny = true;
                echo "<tr>";
                echo "<td>" . $row['idCodigo'] . "</td>";
                echo "<td>" . htmlspecialchars($row['codigo']) . "</td>";
                echo "<td>" . htmlspecialchars($row['descripcion']) . "</td>";
                echo "<td>" . htmlspecialchars($row['tipo']) . "</td>";
                echo "<td>" . $row['total_tools'] . "</td>";
                echo "<td><strong>" . $row['available_tools'] . "</strong></td>";
                echo "<td><a href='?idCodigo=" . $row['idCodigo'] . "'>Check Details</a></td>";
                echo "</tr>";
            }
            
            if (!$hasAny) {
                echo "<tr><td colspan='7'>No products with tools found in database</td></tr>";
            }
            
            echo "</table>";
            sqlsrv_free_stmt($stmtAllTools);
        }
        ?>
        
    <?php endif; ?>

    <hr>
    <p><a href="../exitord.php">← Back to Exit Form</a> | <a href="diagnostic_tool_check.php">Reset</a></p>

</body>
</html>

<?php
sqlsrv_close($conn);
?>