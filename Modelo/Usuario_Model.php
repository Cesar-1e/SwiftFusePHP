<?php
require_once(RUTA_APP . "Modelo/Entidad/Usuario_Entity.php");

//Clase de muestra
class UsuarioMode{
    private $conexion;
    private $mensaje = null;
    private $entidad;

    public function __construct(){
        $this->conexion = new Conexion;
        if($this->conexion->error != null){
            handler(E_ERROR, $this->conexion->error, __FILE__, __LINE__);
        }

        $this->entidad = new Usuario;
    }

    public function getMensaje(){
        return $this->mensaje;
    }

    public function getEntidad(){
        return $this->entidad;
    }

    public function save(){
        $exito = false;
        try{
            $this->conexion->transaccion();
            $entidad = $this->entidad;
            $execute = function($sql){$this->conexion->exec($sql);};
            //Insertar persona
            $sql = "INSERT INTO persona(idTipoDocumento, documento, nombre, apellido, estrato, zonaResidencial, idCiudad, direccion, telefono, genero, fechaNacimiento, idPais, idEps, tipoVinculEPS, idArl, idEscolaridad, idRH, dominancia, idProfesion, correo) VALUES ";
            $sql .= "('" . $entidad->getTipoDocumento()->getId() . "','" . $entidad->getDocumento() . "','" . $entidad->getNombre() . "','" . $entidad->getApellido() . "','" . $entidad->getEstrato() . "','" . $entidad->getZonaResidencial() . "',
                '" . $entidad->getCiudad()->getId() . "','" . $entidad->getDireccion() . "','" . $entidad->getTelefono() . "','" . $entidad->getGenero() . "','" . $entidad->getFechaNacimiento() . "','" . $entidad->getPais()->getId() . "',
                '" . $entidad->getEPS()->getId() . "','" . $entidad->getTipoVinculEPS() . "','" . $entidad->getARL()->getId() . "','" . $entidad->getEscolaridad()->getId() . "','" . $entidad->getRH()->getId() . "','" . $entidad->getDominancia() . "',
                '" . $entidad->getProfesion()->getId() . "','" . $entidad->getCorreo() . "');";
            $execute($sql);

            //Insertar usuario
            $entidad->setPassword($entidad->getDocumento());
            $subSql = "SELECT idPersona FROM persona WHERE documento = '" . $entidad->getDocumento() . "'";
            $sql = "INSERT INTO usuario(idPersona, contrasenia) VALUES 
                    ((" . $subSql . "),'" . $entidad->getPassword() . "');";
            $execute($sql);

            //Insertar rol de Aprendiz
            $sql = "INSERT INTO usuariorol(idUsuario) VALUES 
                    ((SELECT idUsuario FROM usuario WHERE idPersona = (" . $subSql . ")));";
            $execute($sql);
            if($this->conexion->commit()){
                $exito = true;
                $this->mensaje = $this->encriptar($entidad->getDocumento());
            }else{
                $this->mensaje = $this->conexion->error;
                $this->conexion->rollBack();
            }
        }catch(PDOException $ex){
            $this->mensaje = $ex->getMessage();
            $this->conexion->rollBack();
        }
        return $exito;
    }
}
?>