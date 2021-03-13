<?php

$entity = strip_tags(trim(filter_input(INPUT_POST, 'entity', FILTER_DEFAULT)));
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
     * Update the historic for this user to not update the database on front
     */
    if (file_exists(PATH_HOME . "_cdn/store/historic.json")) {
        \Helpers\Helper::createFolderIfNoExist(PATH_HOME . "_cdn/userSSE/{$_SESSION['userlogin']['id']}");
        \Config\Config::createFile(PATH_HOME . "_cdn/userSSE/{$_SESSION['userlogin']['id']}/{$entity}.json", json_decode(file_get_contents(PATH_HOME . "_cdn/store/historic.json"), !0)[$entity]);
    }

    /**
     * Check Error
     */
    if (!is_numeric($data['data'])) {
        $data['error'] = "Erro ao salvar";
        $data['response'] = 2;
    } else {
        $o = \Entity\Entity::exeRead($entity, $data['data']);
        $data['data'] = (!empty($o) && !empty($o[0]) ? $o[0] : []);
    }
} else {
    $data['data'] = $data['error'];
    $data['response'] = 2;
}