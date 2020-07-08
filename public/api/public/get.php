<?php

$entity = $variaveis[0];
if(file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {

    $permission = \Config\Config::getPermission();

    /**
     * Se anonimo tiver permissão para leitura
     */
    if ((!empty($permission[0][$entity]) && $permission[0][$entity]['read']) || (!empty($_SESSION['userlogin']) && ($_SESSION['userlogin']['setor'] === "admin" || $permission[$_SESSION['userlogin']['setor']][$entity]['read']))) {
        $result = [];
        $limite = $variaveis[1] ?? 100000000;
        $offset = $variaveis[2] ?? 0;

        $read = new \Conn\Read();
        $read->exeRead($entity, "LIMIT {$limite} OFFSET {$offset}");
        if($read->getResult())
            $result = $read->getResult();

        $data['data'] = [$entity => $result];
    } else {
        $data = ['response' => 2, 'error' => 'sem permissão de leitura para esta entidade'];
    }

} else {
    $data = ['response' => 2, 'error' => 'entidade não existe'];
}