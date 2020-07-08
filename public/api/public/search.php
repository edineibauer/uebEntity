<?php

/**
 * Busca registros com base em um campo e que possua parte do valor buscado
 */

$entity = strip_tags(trim($variaveis[0]));
if (!empty($variaveis[1])) {
    $campo = strip_tags(trim($variaveis[1]));
    $busca = strip_tags(trim($variaveis[2]));
    if (file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {

        $permission = \Config\Config::getPermission();

        /**
         * Se anonimo tiver permissão para leitura
         */
        if ((!empty($permission[0][$entity]) && $permission[0][$entity]['read']) || (!empty($_SESSION['userlogin']) && ($_SESSION['userlogin']['setor'] === "admin" || $permission[$_SESSION['userlogin']['setor']][$entity]['read']))) {
            $result = [];
            $limite = $variaveis[3] ?? 100000000;
            $offset = $variaveis[4] ?? 0;

            $read = new \Conn\Read();

            $where = "WHERE {$campo} LIKE '%{$busca}%'";
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