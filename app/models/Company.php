<?php namespace app\models;
use \app\core\{
    Error,
    Query
};

class Company extends Query{

    public 
        $id, $nombre, $fecha, $sector, $plan, $ultimo_acceso, $nif,
        $nombre_empresa, $apellidos, $email, $telefono, $calle, $numero, $piso, $escalera, $poblacion, $CP, $provincia, $pais,
        $pathLogo; 
    protected
        $data = null;
    const 
        TABLE_ADMIN = 'admin_empresas';

    function __construct($arg = null){
        // Conectamos o creamos la tabla de administrcion de empresas si no existe
        if(!parent::__construct('empresas', self::TABLE_ADMIN)) $this->initializeDB();
  
        if($arg){
            if (is_int($arg)){
                $d = $this->getById($arg);
            } else if (is_string($arg)){
                $d = $this->getBy(['nombre'=>$arg], '*', true);
                $d['nombre_empresa'] = $d['nombre'];
            }
            if($d) {
                $this->loadData($d);
                parent::__construct('facturacion', 'admin_empresas');
                $this->loadData($this->getById($this->id));
                $this->pathLogo = \URL\COMPANIES . $this->nombre_empresa . '/logo.png'; 
            }
        }
    }

    /**
     * Crea una nueva aplicación
     * Dividimos los datos de las dos tablas la de empresas y la base datos de la app
     * Guardamos los datos de la bd empresas
     * Creamos la base datos de la aplicacion
     * Creamos la carpeta para los archivos de configuración de la aplicación
     */
    public function new(Object $Data){

        $Data->validate(['nombre_empresa', 'nif' ,'sector', 'nombre_usuario', 'email', 'password'], true);
        $Data->codifyAttr('nombre_empresa');
        $Data->set('nombre', $Data->nombre_empresa);
        $this->loadData($Data->getAll());
        $this->config = parse_ini_file(\FILE\CONN);
        $this->db = $this->config["prefix"]  . $this->nombre; 
        
        // Definimos la base de datos por defecto
        define('CODE_COMPANY', $this->nombre);
        define('NAME_COMPANY', ucwords(CODE_COMPANY));

        // Registro de la tabla empresas
        if(
            $this->id =  $this->add([
                 'nombre' => $this->nombre, 
                 'nif' => $this->nif, 
                 'sector' => $this->sector
             ])
        ){
            // Creamos la base de datos
            
            try{
                $this->createDb($this->db);
                $this->createTables();
                //Añadimos el usuario administrador
                $Data->set('nombre', $Data->nombre_usuario); 
                $Data->set('nivel', 2); 

                $User = new User;
                if (!$User->new($Data)) throw new \Exception('E019'); 
                // Creamos carpeta con configuración y archivos
                $this->createFolder();
                return true; 
            
            } catch( \Exception $e){
                return Error::array($e->getMessage());
            }
        } else {
            return Error::array('E011');    
        }
    }

    private function createFolder(){
        $folder = \PUBLICF\COMPANIES . $this->nombre;
        if (!file_exists($folder)){
            mkdir($folder, 0750);
            copy(\PUBLICF\TEMPLATE . 'config.ini', $folder . '/config.ini' );
        } else{
            throw new Error('E017');
        }
    }
    // Extraemos el prefijo por defecto para las bbdd de la aplicación
    // Creamos la base de datos con el nombre correspondiente
    private function createDb($db){
        $credentials = parse_ini_file(\FILE\CONN);
        $dsn =  'mysql:host=' . $credentials["host"] . ';port='. $credentials["port"];
        $this->conn = $this->connect($dsn, $credentials[$this->user]);
        if(!$this->query("CREATE DATABASE $db COLLATE utf8_spanish2_ci;")) throw new Error('E013');
        else return true;  
    }
    private function createTables(){
        $newConn = new Query(null, $this->db);
        $newConn->pdo->beginTransaction();
            $newConn->query(file_get_contents(\FOLDERS\DB . 'app/usuarios.sql'));
            $newConn->query(file_get_contents(\FOLDERS\DB . 'app/direcciones.sql'));
            $newConn->query(file_get_contents(\FOLDERS\DB . 'app/telefonos.sql'));
            $newConn->query(file_get_contents(\FOLDERS\DB . 'app/articulos.sql'));
            $newConn->query(file_get_contents(\FOLDERS\DB . 'app/login.sql'));
            $newConn->query(file_get_contents(\FOLDERS\DB . 'app/tipo_iva.sql'));
            $newConn->query(file_get_contents(\FOLDERS\DB . 'app/tickets.sql'));
            $newConn->query(file_get_contents(\FOLDERS\DB . 'app/lineas.sql'));
            $newConn->query(file_get_contents(\FOLDERS\DB . 'app/historial.sql'));
            $newConn->query(file_get_contents(\FOLDERS\DB . 'app/tokens.sql'));
        
        if(!$newConn->pdo->commit()) throw new Error('E014');
    }
    // getters y setters
    function id(int $arg = null){
        if($arg) $this->{__FUNCTION__} = $arg; 
        return $this->{__FUNCTION__}; 
    }
    function nombre(string $arg = null){
        if($arg) $this->nombre_empresa = $arg; 
        return ucwords($this->nombre_empresa); 
    }
    function data(array $arg = null){
        if($arg) $this->{__FUNCTION__} = $arg; 
        return $this->{__FUNCTION__}; 
    }
    
    function initializeDB(){
        
        $this->createDb(self::TABLE_ADMIN);
    
        $newConn = new Query(null, self::TABLE_ADMIN);
        $newConn->pdo->beginTransaction();
            $newConn->query(file_get_contents(\FOLDERS\DB . 'admin/empresas.sql'));
            $newConn->query(file_get_contents(\FOLDERS\DB . 'admin/facturacion.sql'));
        
        if(!$newConn->pdo->commit()) throw new Error('E014'); 
        else self::__construct();
    }
}