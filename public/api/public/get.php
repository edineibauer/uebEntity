<?php

$var = explode("/", str_replace("get/", "", $_GET['data']));
$entity = $var[0];
if(file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {

    $permission = \Config\Config::getPermission();

    /**
     * Se anonimo tiver permissão para leitura
     */
    if ($permission[0][$entity]['read']) {
        $result = [];
        $limite = $var[1] ?? 100000000;
        $offset = $var[2] ?? 0;

        $read = new \Conn\Read();
        $read->exeRead($entity, "LIMIT {$limite} OFFSET {$offset}");
        if($read->getResult())
            $result = $read->getResult();

        $data['data'] = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } else {
        $data = ['response' => 2, 'error' => 'sem permissão de leitura para esta entidade'];
    }

} else {
    $data = ['response' => 2, 'error' => 'entidade não existe'];
}