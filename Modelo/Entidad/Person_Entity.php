<?php

/**
 * Entidad de Person
 * 
 * Clase de ejemplo
 */
class Person{
    private $id;
    private $name;
    private $email;

    public function getId(){
        return $this->id;
    }

    public function getName(){
        return $this->name;
    }

    public function getEmail(){
        return $this->email;
    }

    public function setId($id){
        $this->id = $id;
    }

    public function setName($name){
        $this->name = filterINPUT($name);
    }

    public function setEmail($email){
        $this->email = filterINPUT($email);
    }
}
?>