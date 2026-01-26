<?php
/**
 * Clase encargada de realizar la conexión mediante PDO a MySQL
 */
class Conexion
{
    private $host = HOST;
    private $usuario = USERNAME;
    private $password = PASSWORD;
    private $database = DATABASE;
    private $ssl_ca = SSL_CA;
    private $dbh;
    private $stmt;
    private $error = null;
    private $isExecuted = false;

    /**
     * Configura la conexión y crea una instancia de PDO
     *
     * En el caso de que falle la conexión, almacena el error en $error
     */
    public function __construct()
    {
        // Configurar conexión
        $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";
        $opciones = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];
        if ($this->ssl_ca) {
            $opciones[PDO::MYSQL_ATTR_SSL_CA] = $this->ssl_ca;
        }

        // Crear una instancia de PDO
        try {
            $this->dbh = new PDO($dsn, $this->usuario, $this->password, $opciones);
        } catch (PDOException $ex) {
            $this->error = $ex->getMessage();
        }
    }

    /**
     * Obtiene el error que se ha presentado
     *
     * @return string|null
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Prepara la consulta
     *
     * @param string $sql
     * @return void
     */
    public function query($sql)
    {
        $this->stmt = $this->dbh->prepare($sql);
        $this->isExecuted = false;
    }

    /**
     * Vincula parámetros a la consulta preparada
     *
     * @param string $parametro El parámetro de la consulta; Ejemplo: :nombre
     * @param mixed $valor El valor a establecer; Ejemplo: Pepito
     * @param int|null $tipo Tipo de dato (PDO::PARAM_*). Si no se especifica, se detecta automáticamente.
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
                case is_array($valor):
                    $tipo = PDO::PARAM_STR;
                    $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
                    break;
                default:
                    $tipo = PDO::PARAM_STR;
                    break;
            }
        }
        $this->stmt->bindValue($parametro, $valor, $tipo);
    }

    /**
     * Ejecuta la consulta preparada
     *
     * @return bool true si la ejecución fue exitosa, false si ocurrió un error
     */
    public function execute()
    {
        try {
            return ($this->isExecuted ?: $this->isExecuted = $this->stmt->execute());
        } catch (PDOException $ex) {
            $this->error = $ex->getMessage();
            if($this->isTransactionActive()){
                throw $ex;
            }else{
                 return false;
            }
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
     * Obtiene los objetos del resultado
     *
     * @return array
     */
    public function getObjects()
    {
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Obtiene un solo objeto del resultado
     *
     * @return object|null
     */
    public function getObject()
    {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Obtiene un array indexado por los nombres de las columnas
     *
     * @return array
     */
    public function getArray()
    {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
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
     * Obtiene la cantidad de filas afectadas
     *
     * @return int
     */
    public function getRecord()
    {
        $this->execute();
        return $this->stmt->rowCount();
    }

    /**
     * Ejecutar Sentencia de Transacción
     *
     * @param  string $sql
     * @return void
     * @deprecated Usar executeTransaction en su lugar
     */
    public function exec($sql)
    {
        $this->dbh->exec($sql);
    }

    /**
     * Inicia una transacción
     *
     * @return void
     */
    public function transaction()
    {
        $this->dbh->beginTransaction();
    }

    /**
     * Ejecuta una consulta dentro de una transacción
     *
     * @param string $sql
     * @return bool true si la ejecución fue exitosa, false si ocurrió un error
     */
    public function executeTransaction($sql)
    {
        try {
            $this->query($sql);
            return $this->execute();
        } catch (PDOException $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
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
     * Deshace todas las operaciones realizadas con la transacción
     *
     * @return void
     */
    public function rollBack()
    {
        $this->dbh->rollBack();
    }

    public function isTransactionActive()
    {
        return $this->dbh->inTransaction();
    }
}
