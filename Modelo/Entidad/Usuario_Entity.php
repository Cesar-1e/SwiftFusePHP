<?php

/**
 * Entidad de Usuario
 *
 * Clase de ejemplo
 *
 * @deprecated 0.9.9 Legacy example entity. New entities belong in app/Models.
 *             Removed in 1.0.
 */
class Usuario{
    private $id;
    private $nick;
    private $password;

    public function getId(){
        return $this->id;
    }

    public function getNick(){
        return $this->nick;
    }

    public function getPassword(){
        return $this->password;
    }

    public function setId($id){
        $this->id = $id;
    }

    public function setNick($nick){
        $this->nick = $nick;
    }

    public function setPassword($password){
        $this->password = $password;
    }
}
?>