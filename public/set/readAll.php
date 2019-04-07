<?php

use Config\Config;
use \Conn\Read;

$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);

$data['data'] = 0;
$setor = empty($_SESSION['userlogin']['setor']) ? 20 : $_SESSION['userlogin']['setor'];

$permissoes = Config::getPermission();
$permissoes = isset($permissoes[$setor]) ? $permissoes[$setor] : [];

if ($setor === "admin" || (!empty($permissoes[$entity]['read']) && $permissoes[$entity]['read'])) {
    $read = new Read();
    $read->exeRead($entity);
    $data['data'] = $read->getResult();
}

