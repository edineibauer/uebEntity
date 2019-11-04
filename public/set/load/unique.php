<?php

$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
$column = filter_input(INPUT_POST, 'column', FILTER_DEFAULT);
$valor = filter_input(INPUT_POST, 'valor', FILTER_DEFAULT);
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

$read = new \Conn\Read();
$read->exeRead($entity, "WHERE {$column} = '{$valor}'" . (!empty($id) && $id > 0 ? " AND id != {$id}" : ""));
$data['data'] = $read->getResult() ? !0 : !1;