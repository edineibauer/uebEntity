<?php

$entity = trim(strip_tags(filter_input(INPUT_POST, 'entity')));
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

$data['data'] = [];
$data['data']['data'] = 0;
if(!empty($entity) && !empty($id)) {
    \Entity\Entity::delete($entity, ['id' => $id]);
    $data['data']['data'] = 1;

    $json = new \Entity\Json();
    $data['data']['historic'] = $json->get("historic")[$entity];
}