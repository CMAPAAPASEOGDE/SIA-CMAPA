<?php
http_response_code(403); // Código HTTP 403: Prohibido
?>
<!DOCTYPE html>
<html>
<head>
    <title>Acceso denegado</title>
</head>
<body>
    <h1>Error 403: Acceso restringido</h1>
    <p>Solo administradores pueden acceder a esta sección.</p>
    <a href="homepage.php">Volver al inicio</a>
</body>
</html>