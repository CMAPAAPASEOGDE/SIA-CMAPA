<?php
// UTILIDADES DE LOGS

function db_conn_or_die() {
  $serverName = "sqlserver-sia.database.windows.net";
  $connectionOptions = [
    "Database" => "db_sia",
    "Uid"      => "cmapADMIN",
    "PWD"      => "@siaADMN56*",
    "Encrypt"  => true,
    "TrustServerCertificate" => false
  ];
  $conn = sqlsrv_connect($serverName, $connectionOptions);
  if ($conn === false) { die("DB error: ".print_r(sqlsrv_errors(), true)); }
  return $conn;
}

function get_user_ip(): string {
  foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
    if (!empty($_SERVER[$k])) {
      $ip = explode(',', $_SERVER[$k])[0];
      return trim($ip);
    }
  }
  return '0.0.0.0';
}

/**
 * Registra un evento.
 * @param int|null $idUsuario  (usa 0 cuando no exista sesión todavía, p.ej. LOGIN_FAIL)
 * @param string   $accion     p.ej. LOGIN_OK, LOGIN_FAIL, ENTRADA_CREATE, SALIDA_CREATE, CAJA_CREATE, REP_WHMS_PDF, CIERRE_CONFIRMAR, etc.
 * @param string   $descripcion texto corto con datos útiles (IDs, periodo, etc.)
 * @param string   $modulo     AUTH | ALMACEN | CAJAS | REPORTES | CIERRE | SISTEMA
 * @param int      $estado     1=ok, 0=error
 */
function log_event($conn, ?int $idUsuario, string $accion, string $descripcion, string $modulo, int $estado = 1): void {
  $sql = "INSERT INTO dbo.Logs(idUsuario, accion, descripcion, modulo, fechaHora, ipUsuario, estado)
          VALUES(?, ?, ?, ?, SYSUTCDATETIME(), ?, ?)";
  $params = [ (int)($idUsuario ?? 0), $accion, $descripcion, $modulo, get_user_ip(), $estado ];
  @sqlsrv_query($conn, $sql, $params);
  // No interrumpo el flujo si falla el log; queda en error_log si quisieras depurarlo
}

/** Borra logs con >30 días (UTC) */
function cleanup_old_logs($conn): void {
  @sqlsrv_query($conn, "DELETE FROM dbo.Logs WHERE fechaHora < DATEADD(day, -30, SYSUTCDATETIME())");
}

/** Ejecuta la limpieza una vez al día por sesión (liviano) */
function logs_boot($conn): void {
  if (empty($_SESSION['__last_log_cleanup']) || $_SESSION['__last_log_cleanup'] < date('Y-m-d')) {
    cleanup_old_logs($conn);
    $_SESSION['__last_log_cleanup'] = date('Y-m-d');
  }
}
