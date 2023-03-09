<?php

$entity = strip_tags(trim(filter_input(INPUT_POST, 'entity')));
$registro = filter_input(INPUT_POST, 'dados', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

if (empty($entity))
    $data['error'] = "Entidade não foi informada";
elseif (!file_exists(PATH_HOME . "entity/cache/{$entity}.json"))
    $data['error'] = "Entidade não existe";
elseif (empty($registro))
    $data['error'] = "Dados não informados";
elseif (!is_array($registro))
    $data['error'] = "Dados precisa ser um objeto";

if (empty($data['error'])) {

    $data['data'] = \Entity\Entity::add($entity, $registro);

    /**
     * Check Error
     */
    if (!is_numeric($data['data'])) {
        $data['error'] = "Erro ao salvar";
        $data['response'] = 2;
    }
} else {
    $data['data'] = $data['error'];
    $data['response'] = 2;
}