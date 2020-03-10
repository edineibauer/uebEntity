<?php

/**
 * Busca registros com base em um campo com valor exato
 */

$var = explode("/", str_replace("find/", "", $_GET['data']));
$entity = strip_tags(trim($var[0]));
if (!empty($var[1])) {
    $campo = strip_tags(trim($var[1]));
    $busca = strip_tags(trim($var[2]));
    if (file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {

        $permission = \Config\Config::getPermission();

        /**
         * Se anonimo tiver permissão para leitura
         */
        if ((!empty($permission[0][$entity]) && $permission[0][$entity]['read']) || (!empty($_SESSION['userlogin']) && ($_SESSION['userlogin']['setor'] === "admin" || $permission[$_SESSION['userlogin']['setor']][$entity]['read']))) {
            $result = [];
            $limite = $var[3] ?? 100000000;
            $offset = $var[4] ?? 0;

            $read = new \Conn\Read();

            $where = "WHERE {$campo} = '{$busca}'";
            if($campo === "id")
                $where = "WHERE id = {$busca}";

            $read->exeRead($entity, $where);
            if ($read->getResult())
                $result = $read->getResult();

            $data['data'] = [$entity => $result];
        } else {
            $data = ['response' => 2, 'error' => 'sem permissão de leitura para esta entidade'];
        }

    } else {
        $data = ['response' => 2, 'error' => 'entidade não existe'];
    }
}