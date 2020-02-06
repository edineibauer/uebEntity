<?php

$var = explode("/", str_replace("put/", "", $_GET['data']));
$entity = $var[0];
if(file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {

    $permission = \Config\Config::getPermission();

    if(!empty($_POST['id']) && is_numeric($_POST['id'])) {
        //update

        /**
         * Se anonimo tiver permissão para leitura
         */
        if ($permission[0][$entity]['update'] || (!empty($_SESSION['userlogin']) && ($_SESSION['userlogin']['setor'] === "admin" || $permission[$_SESSION['userlogin']['setor']][$entity]['update']))) {
            $data['data'] = Entity\Entity::add($entity, $_POST);
        } else {
            $data = ['response' => 2, 'error' => 'sem permissão de atualização para esta entidade'];
        }
    } elseif(!empty($_POST)) {
        //create

        /**
         * Se anonimo tiver permissão para leitura
         */
        if ($permission[0][$entity]['create'] || (!empty($_SESSION['userlogin']) && ($_SESSION['userlogin']['setor'] === "admin" || $permission[$_SESSION['userlogin']['setor']][$entity]['create']))) {
            $data['data'] = Entity\Entity::add($entity, $_POST);
        } else {
            $data = ['response' => 2, 'error' => 'sem permissão de criação para esta entidade'];
        }

    } else {
        $data = ['response' => 2, 'error' => 'dados da entidade não foram submetidos via POST'];
    }

} else {
    $data = ['response' => 2, 'error' => 'entidade não existe'];
}