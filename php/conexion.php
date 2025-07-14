<?php
    session_start();
    class Conectar(
        protected $dbh;
        protected function Conexion(){
            try {
                $connectar = $this->dbh=new
                PDO("sqlsrv:server = tcp:sqlserver-sia.database.windows.net; Database = db_sia", "cmapADMIN", "@siaADMN56*");
                return $connectar;
            }catch (Exception $e){
                print "Error Conexion BD". $e->getMessage() ."<br/>";
                die();
            }
        }
        public static function ruta(){
            return "https://sia-cmapa.azurewebsites.net";
        }
        public static function ruta_Base_menu(){
            return "/sia-cmapa.azurewebsites.net/";
        }
    )
?>