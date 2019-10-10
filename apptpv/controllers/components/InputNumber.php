<?php namespace app\controllers\components;/**
 * Clase de input de tipo numero
 */
class InputNumber extends Input{

    function __construct(Array $data = []){
        $this->type = 'number';
        $this->classLabel = "text-right";
        parent::__construct($data);
    }
}