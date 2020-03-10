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

if (file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {

    $permission = \Config\Config::getPermission();

    if (!empty($dados['id']) && is_numeric($dados['id'])) {
        //update

        /**
         * Se anonimo tiver permissão para leitura
         */
        if ((!empty($permission[0][$entity]) && $permission[0][$entity]['update']) || (!empty($_SESSION['userlogin']) && ($_SESSION['userlogin']['setor'] === "admin" || $permission[$_SESSION['userlogin']['setor']][$entity]['update']))) {

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
            $data = ['response' => 2, 'error' => 'sem permissão de atualização para esta entidade'];
        }
    } elseif (!empty($dados)) {
        //create

        /**
         * Se anonimo tiver permissão para leitura
         */
        if ($permission[0][$entity]['create'] || (!empty($_SESSION['userlogin']) && ($_SESSION['userlogin']['setor'] === "admin" || $permission[$_SESSION['userlogin']['setor']][$entity]['create']))) {

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
            $data = ['response' => 2, 'error' => 'sem permissão de criação para esta entidade'];
        }

    } else {
        $data = ['response' => 2, 'error' => 'dados da entidade não foram submetidos via POST'];
    }

} else {
    $data = ['response' => 2, 'error' => 'entidade não existe'];
}