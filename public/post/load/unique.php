<?php

$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
$column = filter_input(INPUT_POST, 'column', FILTER_DEFAULT);
$valor = filter_input(INPUT_POST, 'valor', FILTER_DEFAULT);
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

$info = json_decode(file_get_contents(PATH_HOME . "entity/cache/info/{$entity}.json"), true)["system"];
$systemWhere = (!empty($info) && !empty($_SESSION["userlogin"]["system_id"]) ? " AND system_id = {$_SESSION["userlogin"]["system_id"]}" : "");

$read = new \Conn\Read();
$read->exeRead($entity, "WHERE {$column} = '{$valor}'" . (!empty($id) && $id > 0 ? " AND id != {$id}" : "") . $systemWhere);
$data['data'] = $read->getResult() ? !0 : !1;