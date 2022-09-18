<?php
/**
 * Clase encarga en realizar la conexión mediante PDO a mysql
 */
class Conexion
{
    private $host = HOST;
    private $usuario = USERNAME;
    private $password = PASSWORD;
    private $database = DATABASE;
    private $dbh;
    private $stmt;
    private $error = null;
    private $isExecuted = false;
    
    /**
     * Configura la conexiín y crea una instancia de PDO
     * 
     * En el caso de que falle la conexión, almacena el error en $error
     *
     * @return void
     */
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
    
    /**
     * Obtiene el error que se ha presentado
     *
     * @return void
     */
    public function getError()
    {
        return $this->error;
    }
    
    /**
     * Preparamos la consulta
     *
     * @param  string $sql
     * @return void
     */
    public function query($sql)
    {
        $this->stmt = $this->dbh->prepare($sql);
        $this->isExecuted = false;
    }

        
    /**
     * Vincula la consulta con bind
     *
     * @param  string $parametro El parametro de la consulta; Example :nombre
     * @param  mixed $valor El valor a establecer; Example: Pepito
     * @param  string $tipo (1)PDO::PARAM_INT | (5)PDO::PARAM_BOOL | (0)PDO::PARAM_NULL | (2)PDO::PARAM_STR
     * @return void
     */
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

        
    /**
     * Ejecuta la consulta
     *
     * @return bool true -> Ejecución exitosa, false -> Se presento un erro y se almaceno en $error
     */
    public function execute()
    {
        try {
            return ($this->isExecuted ?: $this->isExecuted = $this->stmt->execute());
        } catch (PDOException $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
    }
    
    /**
     * Comprueba si estamos conectados al motor
     * 
     * En el caso de no estar conectados, se conecta al motor
     *
     * @return void
     */
    public function isConnected()
    {
        try {
            $this->query('SELECT 1');
            if ($this->execute() == null) $this->__construct();
        } catch (PDOException $ex) {
            $this->__construct();
        }
    }

        
    /**
     * Obtiene los objetos
     *
     * @return array Object
     */
    public function getObjects()
    {
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_OBJ);
    }

        
    /**
     * Obtiene un solo objeto
     *
     * @return Object
     */
    public function getObject()
    {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_OBJ);
    }

        
    /**
     * Obtiene los array indexado por los nombres de las columnas del conjunto de resultados
     *
     * @return array
     */
    public function getsArray()
    {
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el array indexado por los nombres de las columnas del conjunto de resultados
     *
     * @return array
     */
    public function getArray()
    {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

        
    /**
     * Devuelve un array no indexado por las columnas
     *
     * @return array
     */
    public function getArrayWithoutIndexByColumn()
    {
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_NUM);
    }

    
    /**
     * Obtiene cantidad de filas
     *
     * @return int
     */
    public function getRecord()
    {
        $this->execute();
        return $this->stmt->rowCount();
    }

        
    /**
     * Iniciar Transaccion
     *
     * @return void
     */
    public function transaction()
    {
        $this->dbh->beginTransaction();
    }

        
    /**
     * Ejecutar Sentencia de Transacción
     *
     * @param  string $sql
     * @return void
     */
    public function exec($sql)
    {
        $this->dbh->exec($sql);
    }

        
    /**
     * Confirma las ejecuciones de la transacción
     *
     * @return bool
     */
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

        
    /**
     * Deshacer todas las operaciones realizadas con la transacción
     *
     * @return void
     */
    public function rollBack()
    {
        $this->dbh->rollBack();
    }
}
