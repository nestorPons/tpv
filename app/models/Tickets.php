<?php namespace app\models;
use \app\core\{Query, Data, Error};
class Tickets extends Query{
    public $id, $id_empleado, $id_cliente, $estado, $fecha, $hora;
    protected $table = 'tickets';
    function __construct($args = null){

        parent::__construct();
        if(is_int($args)){
            $this->loadData(
                $this->getById($args)
            );
        }else{
            //$this->new();
        }
/*         $data = ($id) ? $this->getById($id) : $this->getLast();
        $date = new \DateTime($data[0]['fecha']);
        $data['hora'] = $date->format('H:i'); 
        $data['fecha'] = $date->format('d/m/Y');
        $this->loadData($data); */
    }
    function last(){
        $data = $this->getLast();
        $this->loadData($data);
        $date = new \DateTime($data['fecha']);
        $this->hora = $date->format('H:i'); 
        $this->fecha = $date->format('d/m/Y');
    }
    function new(Object $Data){
        $lines = $Data->lines; 

        $Data->filter(new Tickets); 
        $DateTime = new \DateTime;
        $Data->fecha = $DateTime->format('Y-m-d H:i');
        $this->id = $this->add($Data->toArray());
        foreach($lines as $line){
            $Line = new Lines;
            $Line->add([
                'id_ticket' => $this->id,
                'articulo' => intval($line['articulo']),
                'precio'   => floatval($line['precio']),
                'cantidad' => intval($line['cantidad']),
                'dto'      => floatval($line['dto'])
            ]);
        }  
        return ['id' => $this->id]; 
    }
    // Método genérico de captura de tickets con sus lineas
    function get(int $id, bool $all = false){
        $Data = new Data($this->getById($id));
        $Lines = new Lines; 
        $lines = $Lines->getBy(['id_ticket'=>$id]);
        $Data->addItem($lines, 'lines');

        if($all) return $Data;
        else if(@$Data->estado == 1) return $Data;
        else return false; 
    }
}