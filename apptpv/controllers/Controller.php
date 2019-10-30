<?php 
namespace app\controllers;
use \app\models\Company;
use \app\core\{Error, Data};
/**
 * Clase para ser expansión de otras subclases o clases dedicadas 
 * Tiene lo mínimo para la creación de una subclase: 
 *  Método para requerir una vista
 *  Método para requerir datos a los modelos (abstracto)
 *  Método para añadir/editar/borrar datos a los modelos (abstracto)
 */
class Controller{
    protected $conn, $controller, $action, $data;
    public $result = null;
    private $Model; 
    private $db = CONN['db'];
    
    function __construct(String $action, $controller = null, $Data = null){
        $this->action = strtolower($action);
        $this->controller =strtolower($controller ?? $this->getController());
        $this->Data = $Data; 

        // Constructor alternativo básico
        $this->result = (method_exists($this, $this->action)) 
                        ? $this->{$this->action}($Data)
                        // Si no encuentra el métodod en el controlador los busca en el modelo
                        : $this->exec ($this->action, ''); 
        }
    protected function view($data = null){
        $arr_data = (is_object($data)) ? $data->toArray() : $data;
        // Carpetas donde buscar las vistas
        $files[] = \FOLDERS\VIEWS . $this->controller . '.phtml'; 
        $files[] = \VIEWS\LOGIN . $this->controller . '.phtml';
        $files[] = \VIEWS\ADMIN . $this->controller . '.phtml';
        $files[] = \VIEWS\USERS . $this->controller . '.phtml';
        $files[] = \VIEWS\ADMIN\SECTIONS . $this->controller . '.phtml';

        foreach($files as $file){
            if(file_exists($file)) return $this->printView($file, $arr_data);
        }        
    }
    /**
     * Método genérico para guardar registros comprueba que es nuevo o edicion y envia los datos al metodo apropiado
     */
    protected function save(Data $Data){
        if($Data->id == -1 ) return $this->new($Data);
        else return $this->update($Data);
    }
        /**
     * Método por defecto de agregación de registros a la base de datos
     */
    protected function new(){
        return $this->exec('new', 'add');
    }
    /**
     * Método genérico para actualizar registros
     */
    protected function update(){
        return $this->exec('save', 'saveById'); 
    }
    /**
     * Método por defecto de consulta de datos 
     */
    protected function get(){
        return $this->exec('get', 'getById');
    }
    protected function printView(String $route, array $data = null){

        $Company = new Company($this->db);
        if($data){
            foreach($data as $key => $val){
                ${$key} = $val;
            }
        }

        return require_once $route;
    }
    /**
     * Método por defecto de consulta de datos entre parametros
     */
    protected function getBetween(){
        return $this->exec('between', 'getBetween');
    }
    /**
     * Método por defecto de eliminación de registros por id
     */
    protected function del(){
        return $this->exec('del', 'deleteById');
    }
    private function getController(){
        $arr_controller= explode('\\',get_class($this));
        $controller = end($arr_controller);
        return strtolower($controller);
    }
    protected function getModel(){
        return (file_exists(\FOLDERS\MODELS . ucfirst($this->controller) . '.php'))
            ? '\\app\\models\\' . ucfirst($this->controller)
            : '\\app\\core\\Query';
    }
    private function exec (String $method, String $method_generic){
        
        $name_model = $this->getModel(); 
        $model = new $name_model($this->controller);
        $model->id = $this->Data->id ?? null;
        // Si no llevamos datos, no pasamos el objeto data 
        // Para poder utilizar directamente con Query
        if($this->Data->isEmpty()){
            if (method_exists($model, $method))return  $model->{$method}();
            else return $model->{$method_generic}();
        } else {
            if (method_exists($model, $method)) return  $model->{$method}($this->Data);
            else return $model->{$method_generic}($this->Data->toArray());
        }
    }
}