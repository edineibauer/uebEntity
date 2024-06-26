<?php

$data['data'] = 1;
$entity = trim(strip_tags(filter_input(INPUT_POST, 'entity')));
$dados = filter_input(INPUT_POST, 'data', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

if(!empty($entity)) {
    foreach ($dados as $i => $datum) {
        if (empty($datum['db_action']))
            $datum['db_action'] = "create";

        switch ($datum['db_action']) {
            case 'create':
            case 'update':
                $id = \Entity\Entity::add($entity, $datum);
                break;
            case 'delete':
                $id = \Entity\Entity::delete($entity, $datum['id']);
        }

        if (isset($id) && !is_int($id))
            $dados['data'][$i] = $id;
    }
}