<?php

$var = explode("/", str_replace("put/", "", $_GET['data']));
$entity = $var[0];
if(file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {

    $dados = $_POST;

    if(!empty($dados['id']) && is_numeric($dados['id'])) {
        //update
        $data['data'] = [$entity => \Helpers\Helper::postRequest(HOME . "set/up/entity", $dados)];
    } elseif(!empty($dados)) {
        //create
        $data['data'] = [$entity => \Helpers\Helper::postRequest(HOME . "set/up/entity", $dados)];

    } else {
        $data = ['response' => 2, 'error' => 'dados da entidade não foram submetidos via POST'];
    }

} else {
    $data = ['response' => 2, 'error' => 'entidade não existe'];
}