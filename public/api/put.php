<?php

$entity = $variaveis[0];

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