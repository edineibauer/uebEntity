<?php

$var = explode("/", str_replace("search/", "", $_GET['data']));
$entity = $var[0];
if (!empty($var[1])) {
    $busca = strip_tags(trim($var[1]));
    if (file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {

        $permission = \Config\Config::getPermission();

        /**
         * Se anonimo tiver permissão para leitura
         */
        if ($permission[0][$entity]['read']) {
            $result = [];
            $limite = $var[1] ?? 100000000;
            $offset = $var[2] ?? 0;

            $dic = \Entity\Metadados::getDicionario($entity);
            $rev = \Entity\Metadados::getRelevant($entity);

            $read = new \Conn\Read();
            $read->exeRead($entity, "WHERE {$dic[$rev]['column']} LIKE '%{$busca}%'");
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