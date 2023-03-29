<?php

$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
$column = filter_input(INPUT_POST, 'column', FILTER_DEFAULT);
$valor = filter_input(INPUT_POST, 'valor', FILTER_DEFAULT);
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

$info = json_decode(file_get_contents(PATH_HOME . "entity/cache/info/{$entity}.json"), true)["system"];

$systemWhere = "WHERE {$column} = :v";
$place = ["v" => $valor];

if((!empty($id) && $id > 0 ?  : "")) {
    $systemWhere .= " AND id != :idd";
    $place["idd"] = $id;
}

if(!empty($info) && !empty($_SESSION["userlogin"]["system_id"])) {
    $systemWhere .= " AND system_id = :ssid";
    $place["ssid"] = $_SESSION["userlogin"]["system_id"];
}

$read = new \Conn\Read();
$read->exeRead($entity, $systemWhere, $place);
$data['data'] = $read->getResult() ? !0 : !1;