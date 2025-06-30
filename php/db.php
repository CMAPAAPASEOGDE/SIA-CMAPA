<?php
/* ───────────────────────────────────────────────────────────────────
   Conexión PDO adaptable:
   • Local  : usa SSL con .pem
   • Azure  : conecta sin .pem (Azure confía en Azure SQL por defecto)
   ─────────────────────────────────────────────────────────────────── */
$server   = "dbsia-inventory.mysql.database.azure.com";
$database = "dbSiacmapa";
$username = "SIAdmin";
$password = "serVEr*56.sQL#21$";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

// Detecta si el host incluye “localhost” o “127.”  → entorno local
$isLocal = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false
        || strpos($_SERVER['HTTP_HOST'] ?? '', '127.')      !== false;

// Si es local, agrega SSL con la ruta del certificado .pem
if ($isLocal) {
    $sslPem = __DIR__ . '/../certs/BaltimoreCyberTrustRoot.crt.pem';
    if (!file_exists($sslPem)) {
        die("⚠️  Certificado SSL no encontrado en: $sslPem");
    }
    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslPem;
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false; // omite verificación estricta
}

try {
    $conn = new PDO(
        "mysql:host=$server;dbname=$database;charset=utf8",
        $username,
        $password,
        $options
    );
} catch (PDOException $e) {
    die("❌ Error de conexión: " . $e->getMessage());
}
?>
