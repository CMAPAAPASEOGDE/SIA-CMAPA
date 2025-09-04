<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../index.php"); exit(); }
require_once __DIR__.'/month_close_utils.php';

$conn   = db_conn_or_die();
$period = compute_close_period();
$idUser = (int)$_SESSION['user_id'];

$idCierre = persist_cierre_y_snapshot($conn, $period, $idUser);

require_once __DIR__.'/log_utils.php';
$conn = db_conn_or_die(); logs_boot($conn);
log_event(
  $conn,
  (int)$_SESSION['user_id'],
  'CIERRE_CONFIRMAR',
  'Cierre '.$period['etiqueta'].' '.$period['fechaInicio'].' a '.$period['fechaFin'].' idCierre='.$idCierre,
  'CIERRE',
  1
);


// Redirige con mensaje
header("Location: ../mnthcnf.php?ok=1&idCierre=".$idCierre);
exit;
