<?php

$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

\Entity\Entity::delete($entity, ['id' => $id]);
$data['data'] = [];
$data['data']['data'] = 1;

$json = new \Entity\Json();
$data['data']['historic'] = $json->get("historic")[$entity];