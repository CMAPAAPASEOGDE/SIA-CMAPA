<?php
// php/actualizar_usuario.php
session_start();

if (!isset($_SESSION['user_id']) || (int)($_SESSION['rol'] ?? 0) !== 1) {
  header("Location: ../index.php");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: ../admnusredsrch.php");
  exit();
}

$idUsuario = (int)($_POST['idUsuario'] ?? 0);
$rol       = (int)($_POST['rol'] ?? 0);
$apodo     = trim((string)($_POST['apodo'] ?? ''));
$estatus   = isset($_POST['estatus']) ? (int)$_POST['estatus'] : 0;
$cambiar   = (int)($_POST['cambiar_contra'] ?? 0);

$pwd  = (string)($_POST['password'] ?? '');
$pwd2 = (string)($_POST['confirm_password'] ?? '');

if ($idUsuario <= 0) { $_SESSION['error_actualizacion'] = "Usuario inválido."; header("Location: ../admnusredt.php"); exit(); }
if (!in_array($rol, [1,2,3], true)) { $_SESSION['error_actualizacion'] = "Rol inválido."; header("Location: ../admnusredt.php"); exit(); }
if (!in_array($estatus, [0,1], true)) { $_SESSION['error_actualizacion'] = "Estatus inválido."; header("Location: ../admnusredt.php"); exit(); }

if ($cambiar === 1) {
  if ($pwd === '' || $pwd2 === '') { $_SESSION['error_actualizacion'] = "Debes capturar la nueva contraseña y su confirmación."; header("Location: ../admnusredt.php"); exit(); }
  if ($pwd !== $pwd2) { $_SESSION['error_actualizacion'] = "Las contraseñas no coinciden."; header("Location: ../admnusredt.php"); exit(); }
  if (strlen($pwd) < 8) { $_SESSION['error_actualizacion'] = "La contraseña debe tener al menos 8 caracteres."; header("Location: ../admnusredt.php"); exit(); }
}

$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = [
  "Database" => "db_sia",
  "Uid"      => "cmapADMIN",
  "PWD"      => "@siaADMN56*",
  "Encrypt"  => true,
  "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
  $_SESSION['error_actualizacion'] = "Error de conexión: " . print_r(sqlsrv_errors(), true);
  header("Location: ../admnusredt.php"); exit();
}

// ===== UPDATE con control de filas afectadas =====
if ($cambiar === 1) {
  // OJO: en producción usar password_hash
  $sql    = "UPDATE usuarios SET idRol = ?, apodo = ?, estatus = ?, contrasena = ? WHERE idUsuario = ?";
  $params = [$rol, $apodo, $estatus, $pwd, $idUsuario];
} else {
  $sql    = "UPDATE usuarios SET idRol = ?, apodo = ?, estatus = ? WHERE idUsuario = ?";
  $params = [$rol, $apodo, $estatus, $idUsuario];
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
  $_SESSION['error_actualizacion'] = "No se pudo actualizar el usuario. Detalle: " . print_r(sqlsrv_errors(), true);
  sqlsrv_close($conn);
  header("Location: ../admnusredt.php"); exit();
}

$rows = sqlsrv_rows_affected($stmt);
// $rows puede ser 0 si los valores son idénticos; lo tratamos como OK.
if ($rows === false) {
  $_SESSION['error_actualizacion'] = "Actualización desconocida. Detalle: " . print_r(sqlsrv_errors(), true);
  sqlsrv_close($conn);
  header("Location: ../admnusredt.php"); exit();
}

// Actualiza el objeto de sesión usado para pre-llenar
if (isset($_SESSION['editar_usuario']) && is_array($_SESSION['editar_usuario'])) {
  $_SESSION['editar_usuario']['idRol']   = $rol;
  $_SESSION['editar_usuario']['apodo']   = $apodo;
  $_SESSION['editar_usuario']['estatus'] = $estatus;
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

// OK -> confirmación
header("Location: ../admnusredtcf.php");
exit();
