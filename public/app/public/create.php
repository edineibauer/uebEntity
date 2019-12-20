<?php

/**
 * Alias to put.php
 */

$var = explode("/", str_replace("put/", "", $_GET['data']));
$entity = $var[0];
if(file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {

    $dados = $_POST;
    $permission = \Config\Config::getPermission();

    if(!empty($dados['id']) && is_numeric($dados['id']))
        unset($dados['id']);

    if(!empty($dados)) {
        //create

        /**
         * Se anonimo tiver permissão para leitura
         */
        if ($permission[0][$entity]['create']) {
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