<?php

$entity = strip_tags(trim(filter_input(INPUT_POST, 'entity', FILTER_DEFAULT)));
$dados = json_decode(filter_input(INPUT_POST, 'dados', FILTER_DEFAULT), true);

if(file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {
    $id = \Entity\Entity::add($entity, $dados);
    $data['data'] = [];

    $read = new \Conn\Read();
    $read->exeRead($entity, "WHERE id = :id", "id={$id}");
    $data['data']['error'] = is_object($id) ? $id : 0;
    $data['data']['data'] = ($read->getResult() ? $read->getResult()[0] : 0);

    $json = new \Entity\Json();
    $data['data']['historic'] = $json->get("historic")[$entity];
}