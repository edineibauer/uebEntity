<?php

$entity = $variaveis[0];
if(file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {

    $result = [];
    $limite = $variaveis[1] ?? 100000000;
    $offset = $variaveis[2] ?? 0;

    $read = new \Conn\Read();
    $read->exeRead($entity, "LIMIT {$limite} OFFSET {$offset}");
    if($read->getResult())
        $result = $read->getResult();

    $data['data'] = [$entity => $result];

} else {
    $data = ['response' => 2, 'error' => 'entidade não existe'];
}