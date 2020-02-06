<?php

$var = explode("/", str_replace("put/", "", $_GET['data']));
$entity = $var[0];

if (file_exists(PATH_HOME . "entity/cache/{$entity}.json") && !empty($_POST)) {
    //create or update
    $data['data'] = Entity\Entity::add($entity, $_POST);
} else {
    $data = ['response' => 2, 'error' => empty($_POST) ? "dados não foram recebidos via POST" : 'entidade não existe'];
}