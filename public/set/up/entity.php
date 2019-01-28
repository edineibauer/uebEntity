<?php

use Entity\Json;

$entity = strip_tags(trim(filter_input(INPUT_POST, 'entity', FILTER_DEFAULT)));
$dados = json_decode(filter_input(INPUT_POST, 'dados', FILTER_DEFAULT), true);
$data['data'] = ['error' => 0, 'historic' => 0];

if (!empty($entity) && file_exists(PATH_HOME . "entity/cache/{$entity}.json") && !empty($dados) && is_array($dados)) {
    $read = new \Conn\Read();
    $del = new \Conn\Delete();

    foreach ($dados as $i => $dado) {
        if ($dado['db_action'] === "delete") {
            if(is_array($dado['delete'])) {
                foreach ($dado['delete'] as $item)
                    $del->exeDelete($entity, "WHERE id = :id", "id={$item}");
            } elseif(is_numeric($dado['delete'])) {
                $del->exeDelete($entity, "WHERE id = :id", "id={$dado['delete']}");
            }

        } else {

            $registro = $dado;
            unset($registro['db_action']);

            //remove id se existir e for criar
            if($dado['db_action'] === "create" && isset($registro['id']))
                unset($registro['id']);

            $id = \Entity\Entity::add($entity, $registro);

            if(is_numeric($id))
                $dados[$i]['id'] = $id;
            else
                $data['data']['error'] += 1;
        }
    }

    $json = new Json();
    $hist = $json->get("historic");
    $hist[$entity] = strtotime('now');
    $json->save("historic", $hist);

    //salva alterações
    if(!empty($dados)) {
        $store = new Json("update/{$entity}");
        $store->setVersionamento(false);
        $store->save($hist[$entity], $dados);
    }

    $data['data']['historic'] = $hist[$entity];
}