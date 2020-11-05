<?php

$entity = strip_tags(trim($variaveis[0]));
if (!empty($variaveis[1])) {
    $campo = strip_tags(trim($variaveis[1]));
    $busca = strip_tags(trim($variaveis[2]));
    if (file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {

        $result = [];
        $limite = $variaveis[3] ?? 100000000;
        $offset = $variaveis[4] ?? 0;

        $del = new \Conn\Delete();

        $where = "WHERE {$campo} = '{$busca}'";
        if($campo === "id")
            $where = "WHERE id = {$busca}";

        $del->exeDelete($entity, $where);
        $data['data'] = [$entity => $del->getResult() ? !0 : !1];

    } else {
        $data = ['response' => 2, 'error' => 'entidade não existe'];
    }
}