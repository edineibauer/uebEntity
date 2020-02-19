<?php

$var = explode("/", str_replace("put/", "", $_GET['data']));
$entity = $var[0];
$dados = [];

if (empty($_POST)) {
    $putfp = fopen('php://input', 'r');
    $putdata = '';
    while ($dataRead = fread($putfp, 1024))
        $putdata .= $dataRead;
    fclose($putfp);

    if (getallheaders()['Content-Type'] === "application/json") {
        $dados = json_decode($putdata, !0);
    } else {
        parse_str($putdata, $dados);
    }
}

if (empty($dados) && !empty($_POST)) {
    $dados = $_POST;

    if (getallheaders()['Content-Type'] === "application/json")
        $dados = json_decode($dados, !0);
}

if (file_exists(PATH_HOME . "entity/cache/{$entity}.json") && !empty($dados)) {

    if(isset($dados[$entity]) && is_array($dados[$entity])) {
        foreach ($dados[$entity] as $dado) {
            //create or update
            $data['data'] = Entity\Entity::add($entity, $dado);
        }
    } else {
        //create or update
        $data['data'] = Entity\Entity::add($entity, $dados);
    }

} else {
    $data = ['response' => 2, 'error' => empty($dados) ? "dados não foram recebidos via POST" : 'entidade não existe'];
}