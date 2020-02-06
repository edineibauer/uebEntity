<?php

$var = explode("/", str_replace("put/", "", $_GET['data']));
$entity = $var[0];
if(file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {

    $dados = $_POST;
    $permission = \Config\Config::getPermission();

    if(!empty($dados['id']) && is_numeric($dados['id'])) {
        //update

        /**
         * Se anonimo tiver permissão para leitura
         */
        if ($permission[0][$entity]['update'] || (!empty($_SESSION['userlogin']) && ($_SESSION['userlogin']['setor'] === "admin" || $permission[$_SESSION['userlogin']['setor']][$entity]['update']))) {
            $data['data'] = \Helpers\Helper::postRequest(HOME . "set/up/entity", $dados);
        } else {
            $data = ['response' => 2, 'error' => 'sem permissão de atualização para esta entidade'];
        }
    } elseif(!empty($dados)) {
        //create

        /**
         * Se anonimo tiver permissão para leitura
         */
        if ($permission[0][$entity]['create'] || (!empty($_SESSION['userlogin']) && ($_SESSION['userlogin']['setor'] === "admin" || $permission[$_SESSION['userlogin']['setor']][$entity]['create']))) {
            $data['data'] = \Helpers\Helper::postRequest(HOME . "set/up/entity", $dados);
        } else {
            $data = ['response' => 2, 'error' => 'sem permissão de criação para esta entidade'];
        }

    } else {
        $data = ['response' => 2, 'error' => 'dados da entidade não foram submetidos via POST'];
    }

} else {
    $data = ['response' => 2, 'error' => 'entidade não existe'];
}