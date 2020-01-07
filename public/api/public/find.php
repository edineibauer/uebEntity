<?php

/**
 * Alias to search.php
 */

$var = explode("/", str_replace("search/", "", $_GET['data']));
$entity = $var[0];
if (!empty($var[1])) {
    $campo = strip_tags(trim($var[1]));
    $busca = strip_tags(trim($var[2]));
    if (file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {

        $permission = \Config\Config::getPermission();

        /**
         * Se anonimo tiver permissão para leitura
         */
        if ($permission[0][$entity]['read']) {
            $result = [];
            $limite = $var[3] ?? 100000000;
            $offset = $var[4] ?? 0;

            $read = new \Conn\Read();
            $read->exeRead($entity, "WHERE {$campo} LIKE '%{$busca}%'");
            if ($read->getResult())
                $result = $read->getResult();

            $data['data'] = $result;
        } else {
            $data = ['response' => 2, 'error' => 'sem permissão de leitura para esta entidade'];
        }

    } else {
        $data = ['response' => 2, 'error' => 'entidade não existe'];
    }
}