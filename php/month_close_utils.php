<?php
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

/* Regla:
   - día > 15 ⇒ mesCerrado = mes actual (inicio = 1° del mes actual)
   - día ≤ 15 ⇒ mesCerrado = mes anterior (inicio = 1° del mes anterior)
   - fin = HOY
*/
function compute_close_period(DateTime $now = null) {
  $now = $now ?: new DateTime('now');
  $day = (int)$now->format('d');

  if ($day > 15) {
    $start = new DateTime($now->format('Y-m-01'));
    $mesCerrado = (int)$now->format('m');
    $anioCerrado = (int)$now->format('Y');
    $afterDay15 = 1;
  } else {
    $start = (new DateTime($now->format('Y-m-01')))->modify('-1 month');
    $mesCerrado = (int)$start->format('m');
    $anioCerrado = (int)$start->format('Y');
    $afterDay15 = 0;
  }

  $end = new DateTime($now->format('Y-m-d'));
  $meses = [1=>"ENERO",2=>"FEBRERO",3=>"MARZO",4=>"ABRIL",5=>"MAYO",6=>"JUNIO",
            7=>"JULIO",8=>"AGOSTO",9=>"SEPTIEMBRE",10=>"OCTUBRE",11=>"NOVIEMBRE",12=>"DICIEMBRE"];
  return [
    'fechaInicio' => $start->format('Y-m-d'),
    'fechaFin'    => $end->format('Y-m-d'),
    'mesCerrado'  => $mesCerrado,
    'anioCerrado' => $anioCerrado,
    'etiqueta'    => $meses[$mesCerrado].' '.$anioCerrado,
    'afterDay15'  => $afterDay15,
  ];
}

/* Movimientos con costo (usa Productos.precio) en [inicio, fin] */
function fetch_movs_con_costos($conn, $inicio, $fin) {
  $sql = "
  SELECT e.fecha AS fecha, 'ENTRADA' AS tipo, p.idCodigo, p.codigo AS sku, p.descripcion,
         CAST(e.cantidad AS INT) AS cantidad, p.precio AS precioUnitario,
         CAST(e.cantidad * p.precio AS DECIMAL(18,2)) AS total,
         NULL AS info
    FROM dbo.Entradas e
    JOIN dbo.Productos p ON p.idCodigo = e.idCodigo
   WHERE e.fecha >= ? AND e.fecha <= ?
  UNION ALL
  SELECT s.fechaSalida AS fecha, 'SALIDA (ORDEN)' AS tipo, p.idCodigo, p.codigo AS sku, p.descripcion,
         CAST(s.cantidad AS INT), p.precio,
         CAST(s.cantidad * p.precio AS DECIMAL(18,2)),
         CONCAT('Orden:', COALESCE(CAST(s.numeroOrden AS VARCHAR(50)),''),' | RPU:',COALESCE(s.rpuUsuario,''),' | Op:',COALESCE(CAST(s.idOperador AS VARCHAR(20)),''))
    FROM dbo.Salidas s
    JOIN dbo.Productos p ON p.idCodigo = s.idCodigo
   WHERE s.fechaSalida >= ? AND s.fechaSalida <= ?
  UNION ALL
  SELECT so.fecha AS fecha, 'SALIDA (SIN ORDEN)' AS tipo, p.idCodigo, p.codigo AS sku, p.descripcion,
         CAST(so.cantidad AS INT), p.precio,
         CAST(so.cantidad * p.precio AS DECIMAL(18,2)),
         CONCAT('Área:',COALESCE(so.areaSolicitante,''),' | Encargado:',COALESCE(so.encargadoArea,''),' | Coment:',COALESCE(so.comentarios,''))
    FROM dbo.SalidaSinorden so
    JOIN dbo.Productos p ON p.idCodigo = so.idCodigo
   WHERE so.fecha >= ? AND so.fecha <= ?
  ORDER BY fecha ASC, tipo ASC, sku ASC";
  $params = [$inicio,$fin,$inicio,$fin,$inicio,$fin];

  $rows=[]; $stmt=sqlsrv_query($conn,$sql,$params);
  if ($stmt) {
    while($r=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)){
      if ($r['fecha'] instanceof DateTime) $r['fecha'] = $r['fecha']->format('Y-m-d');
      $rows[]=$r;
    }
    sqlsrv_free_stmt($stmt);
  }
  return $rows;
}

/* Snapshot actual de cajas (para previsualizar) */
function get_cajas_snapshot_now($conn) {
  $sql="SELECT cj.idCaja, cj.numeroCaja, cc.idCodigo, p.codigo AS sku, p.descripcion,
               CAST(cc.cantidad AS INT) AS cantidad
          FROM dbo.CajaRegistro cj
          JOIN dbo.CajaContenido cc ON cc.idCaja = cj.idCaja
          JOIN dbo.Productos p      ON p.idCodigo = cc.idCodigo
      ORDER BY cj.numeroCaja, p.codigo";
  $rows=[]; $stmt=sqlsrv_query($conn,$sql);
  if ($stmt){ while($r=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) $rows[]=$r; sqlsrv_free_stmt($stmt); }
  return $rows;
}

/* Inserta CierresMes + snapshot de cajas. Devuelve idCierre */
function persist_cierre_y_snapshot($conn, $period, $idUsuario) {
  $sqlIns="INSERT INTO dbo.CierresMes
           (mesCerrado,anioCerrado,fechaInicio,fechaFin,confirmadoPor,etiqueta,afterDay15,fechaConfirmacion)
           OUTPUT INSERTED.idCierre
           VALUES (?,?,?,?,?,?,?,SYSUTCDATETIME())";
  $stmt=sqlsrv_query($conn,$sqlIns,[
    $period['mesCerrado'],$period['anioCerrado'],$period['fechaInicio'],$period['fechaFin'],
    (int)$idUsuario,$period['etiqueta'],(int)$period['afterDay15']
  ]);
  if(!$stmt){ die("No se pudo insertar cierre: ".print_r(sqlsrv_errors(),true)); }
  $row=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC); sqlsrv_free_stmt($stmt);
  $idCierre=(int)$row['idCierre'];

  // snapshot
  $cajas=get_cajas_snapshot_now($conn);
  if($cajas){
    $ins="INSERT INTO dbo.CierreCajasSnapshot (idCierre,idCaja,numeroCaja,idCodigo,cantidad) VALUES (?,?,?,?,?)";
    foreach($cajas as $c){
      sqlsrv_query($conn,$ins,[$idCierre,(int)$c['idCaja'],$c['numeroCaja'],(int)$c['idCodigo'],(int)$c['cantidad']]);
    }
  }
  return $idCierre;
}

function list_cierres($conn,$limit=12){
  $sql="SELECT TOP ($limit) idCierre,etiqueta,fechaInicio,fechaFin,fechaConfirmacion
          FROM dbo.CierresMes
      ORDER BY fechaConfirmacion DESC";
  $res=[]; $stmt=sqlsrv_query($conn,$sql);
  if($stmt){ while($r=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) $res[]=$r; sqlsrv_free_stmt($stmt); }
  return $res;
}
function get_cierre($conn,$idCierre){
  $stmt=sqlsrv_query($conn,"SELECT * FROM dbo.CierresMes WHERE idCierre = ?",[$idCierre]);
  $row=$stmt?sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC):null;
  if($stmt) sqlsrv_free_stmt($stmt);
  return $row;
}
function get_snapshot_cierre($conn,$idCierre){
  $sql="SELECT s.idCaja,s.numeroCaja,s.idCodigo,p.codigo AS sku,p.descripcion,s.cantidad
          FROM dbo.CierreCajasSnapshot s
          JOIN dbo.Productos p ON p.idCodigo=s.idCodigo
         WHERE s.idCierre=?
      ORDER BY s.numeroCaja,p.codigo";
  $rows=[]; $stmt=sqlsrv_query($conn,$sql,[$idCierre]);
  if($stmt){ while($r=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) $rows[]=$r; sqlsrv_free_stmt($stmt); }
  return $rows;
}
