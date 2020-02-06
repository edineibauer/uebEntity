<?php

$var = explode("/", str_replace("put/", "", $_GET['data']));
$entity = $var[0];
$dados = [];

if(empty($_POST)) {
    $putfp = fopen('php://input', 'r');
    $putdata = '';
    while ($dataRead = fread($putfp, 1024))
        $putdata .= $dataRead;
    fclose($putfp);
    parse_str($putdata, $dados);
}

if(empty($dados) && !empty($_POST))
    $dados = $_POST;

if (file_exists(PATH_HOME . "entity/cache/{$entity}.json") && !empty($dados)) {
    //create or update
    $data['data'] = Entity\Entity::add($entity, $dados);
} else {
    $data = ['response' => 2, 'error' => empty($dados) ? "dados não foram recebidos via POST" : 'entidade não existe'];
}