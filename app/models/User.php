<?php namespace app\models;

use \app\core\Query;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use \app\core\Data; 
use \app\core\Error;

class User extends Query{
    protected $id, $dni, $nombre, $email, $fecha_nacimiento, $estado, $nivel, $password, $intentos, $company, $token;
    protected $table = 'usuarios';
    /**
     * $arg puede ser un string email para buscar por email
     * integer buscar por id de usuario
     * false para no crear conexion
     */
    function __construct($arg = null, bool $conn = true){
        $this->company = NAME_COMPANY??null;
        if($conn) parent::__construct();
        if($arg){
            if (is_int($arg)) $this->searchById($arg);
            else if (strpos($arg, '@')) $this->searchByEmail($arg);
        }
    }
    function new(Object $Data){

        if ($this->id = $this->loadData($Data)){
            
            if(
            $this->id = $this->add([
                'dni' =>  $this->dni??null,
                'nombre' => $this->nombre,
                'email' => $this->email??null,
                'fecha_nacimiento' => $this->fecha_nacimiento??null,
                'estado' => $this->estado??0,
                'nivel' => $this->nivel??0,
                'password' => $this->password_hash(),
                'intentos' => $this->intentos??0
            ])) {
                $this->sendMail('mailNewUser.phtml', 'Activacion de la cuenta en '. $this->company);

                return true;
            }
            else return Error::array('E022');
            
        } else throw new \Exception('E060');
    }
    function password_hash(string $pass = null){
        $pass = $pass??$this->password();
        return password_hash($pass, PASSWORD_DEFAULT);
    }
    function searchById(int $arg){

        $data = $this->getById($arg); 
        if($data) return  $this->loadData($data);
        // en caso que no lo encuentre
        Error::die('E025');
    }
    function searchByEmail(string $arg){
        $data = $this->getBy(['email' => $arg]);
        if($data) return  $this->loadData($data);
        // en caso que no lo encuentre
        Error::die('E025');
    }
    function activate(){
        return $this->saveById(['estado'=>1]);
    }
    function save(Object $Data){
        if(property_exists($Data, 'password')) $Data->password = $this->password_hash($Data->password);
        $data = $Data->toArray();
        return $this->saveById($data);
    }
    function resetPassword(){
        return $this->sendMail('mailresetpassword.phtml', $this->company . ' nueva contraseña');
    }
    private function sendMail(string $page, string $subject){

        
        try {
            $Mail = new PHPMailer(true);
            // Configuración para mandar emails
            include_once \FOLDERS\CONFIG . 'mail.php';
            $Mail->IsHTML(true);
            $Mail->isSMTP();
            //definimos el destinatario (dirección y, opcionalmente, nombre)
            $Mail->AddAddress($this->email, $this->nombre);
            //Definimos el tema del email
            $Mail->Subject = $subject ;
            $Mail->Body = 'esto es el cuerpo de la prueba';
            //Para enviar un correo formateado en HTML lo cargamos con la siguiente función. Si no, puedes meterle directamente una cadena de texto.
    
            
            $Token = new Tokens(); 
            $token = $Token->create($this);
            ob_start(); # apertura de bufer
            include( \FOLDERS\VIEWS . $page );
            $htmlStrig = ob_get_contents();
            ob_end_clean(); # cierre de bufer
    
            $Mail->MsgHTML($htmlStrig, dirname(\FOLDERS\VIEWS));
    
            //Y por si nos bloquean el contenido HTML (algunos correos lo hacen por seguridad) una versión alternativa en texto plano (también será válida para lectores de pantalla)
            $Mail->AltBody = 'This is a plain-text message body';
            return $Mail->Send();
           
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

    }
    //getters setters

    function id(int $arg = null){
        if($arg) $this->{__FUNCTION__} = $arg; 
        return $this->{__FUNCTION__}; 
    }
    function email(int $arg = null){
        if($arg) $this->{__FUNCTION__} = $arg; 
        return $this->{__FUNCTION__}; 
    }
    function nombre(int $arg = null){
        if($arg) $this->{__FUNCTION__} = $arg; 
        return $this->{__FUNCTION__}; 
    }
    function dni(int $arg = null){
        if($arg) $this->{__FUNCTION__} = $arg; 
        return $this->{__FUNCTION__}; 
    }
    function nivel(int $arg = null){
        if($arg) $this->{__FUNCTION__} = $arg; 
        return $this->{__FUNCTION__}; 
    }
    function fecha_nacimiento(int $arg = null){
        if($arg) $this->{__FUNCTION__} = $arg; 
        return $this->{__FUNCTION__}; 
    }
    function password(int $arg = null){
        if($arg) $this->{__FUNCTION__} = $arg; 
        return $this->{__FUNCTION__}; 
    }

}