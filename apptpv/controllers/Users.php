<?php

namespace app\controllers;

use \app\models\User;
use\app\core\{Data, Controller};

class Users extends Controller
{

    function __construct(String $action,  $Data = null)
    {
        $this->user = new User($Data->idadmin);
        parent::__construct($action);
    }
    protected function view($data = null)
    {
        $data['type_users'] = ($this->user->isAdmin())
            ? [0 => 'Cliente', 1 => 'Usuario', 2 => 'Administrador']
            : [0 => 'Cliente', 1 => 'Usuario'];

        parent::view($data);
    }
}
