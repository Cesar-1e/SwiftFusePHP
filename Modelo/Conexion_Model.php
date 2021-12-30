<?php
class Conexion
{
    private $host = HOST;
    private $usuario = USERNAME;
    private $password = PASSWORD;
    private $database = DATABASE;
    private $dbh;
    private $stmt;
    private $error = null;

    public function __construct()
    {
        //Configurar conexion
        $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->database;
        $opciones = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        );
        //Crear una instancia de PDO
        try {
            $this->dbh = new PDO($dsn, $this->usuario, $this->password, $opciones);
            $this->dbh->exec("set names utf8");
        } catch (PDOException $ex) {
            $this->error = $ex->getMessage();
        }
    }

    public function getError()
    {
        return $this->error;
    }

    public function query($sql)
    {
        $this->stmt = $this->dbh->prepare($sql);
    }

    //Vincula la consulta con bind
    public function bind($parametro, $valor, $tipo = null)
    {
        if (is_null($tipo)) {
            switch (true) {
                case is_int($valor):
                    $tipo = PDO::PARAM_INT;
                    break;
                case is_bool($valor):
                    $tipo = PDO::PARAM_BOOL;
                    break;
                case is_null($valor):
                    $tipo = PDO::PARAM_NULL;
                    break;
                default:
                    $tipo = PDO::PARAM_STR;
                    break;
            }
        }
        $this->stmt->bindValue($parametro, $valor, $tipo);
    }

    //Ejecuta la consulta
    public function execute()
    {
        try {
            return $this->stmt->execute();
        } catch (PDOException $ex) {
            $this->error = $ex->getMessage();
            return null;
        }
    }

    public function isConnected()
    {
        try {
            $this->query('SELECT 1');
            if ($this->execute() == null) $this->__construct();
        } catch (PDOException $ex) {
            $this->__construct();
        }
    }

    //Obtener los objetos
    public function getObjects()
    {
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_OBJ);
    }

    //Obtener un solo objeto
    public function getObjec()
    {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_OBJ);
    }

    //Obtener cantidad de filas
    public function getRecord()
    {
        $this->execute();
        return $this->stmt->rowCount();
    }

    public function getArray()
    {
        $this->execute();
        return $this->stmt->fecthAll(PDO::FETCH_ASSOC);
    }

    public function getAllData()
    {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function closeCursor()
    {
        return $this->stmt->closeCursor();
    }

    //Iniciar Transaccion
    public function transaction()
    {
        $this->dbh->beginTransaction();
    }

    //Ejecutar Sentencia de Transacción
    public function exec($sql)
    {
        $this->dbh->exec($sql);
    }

    //Confirmar Transacción
    //Return bool
    public function commit()
    {
        try {
            $this->dbh->commit();
            return true;
        } catch (PDOException $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
    }

    //Cancelar Transacción
    public function rollBack()
    {
        $this->dbh->rollBack();
    }
    //

    public function __destruct()
    {
        $this->query('KILL CONNECTION_ID()');
        if ($this->execute()) {
            $this->dbh = null;
            $this->stmt = null;
        }
    }
}
