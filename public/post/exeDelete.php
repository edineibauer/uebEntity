<?php

$entity = trim(strip_tags(filter_input(INPUT_POST, 'entity')));
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

$data['data'] = "";
if(!empty($entity) && !empty($id)) {
    $result = \Entity\Entity::delete($entity, $id);
    $data['data'] = $result;
}
