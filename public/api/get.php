<?php

$var = explode("/", str_replace("get/", "", $_GET['data']));
$entity = $var[0];
if(file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {

    $result = [];
    $limite = $var[1] ?? 100000000;
    $offset = $var[2] ?? 0;

    $read = new \Conn\Read();
    $read->exeRead($entity, "LIMIT {$limite} OFFSET {$offset}");
    if($read->getResult())
        $result = $read->getResult();

    $data['data'] = [$entity => $result];

} else {
    $data = ['response' => 2, 'error' => 'entidade não existe'];
}