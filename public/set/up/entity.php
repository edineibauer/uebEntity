<?php

use Entity\Json;
use \Helpers\Helper;

$entity = strip_tags(trim(filter_input(INPUT_POST, 'entity', FILTER_DEFAULT)));
$dados = json_decode(filter_input(INPUT_POST, 'dados', FILTER_DEFAULT), true)['dados'];
$data['data'] = ['error' => 0, 'data' => '', 'historic' => 0];

if (!empty($entity) && file_exists(PATH_HOME . "entity/cache/{$entity}.json") && !empty($dados) && is_array($dados)) {
    $read = new \Conn\Read();
    $del = new \Conn\Delete();

    foreach ($dados as $i => $dado) {
        if ($dado['db_action'] === "delete") {
            if (is_array($dado['delete'])) {
                foreach ($dado['delete'] as $item)
                    $del->exeDelete($entity, "WHERE id = :id", "id={$item}");
            } elseif (is_numeric($dado['delete'])) {
                $del->exeDelete($entity, "WHERE id = :id", "id={$dado['delete']}");
            }

        } else {

            $registro = $dado;
            unset($registro['db_action']);

            //remove id se existir e for criar
            if ($dado['db_action'] === "create" && isset($registro['id']))
                unset($registro['id']);

            $id = \Entity\Entity::add($entity, $registro);

            if (is_numeric($id)) {
                $read->exeRead($entity, "WHERE id = :id", "id={$id}");
                $action = $dados[$i]['db_action'];
                $data['data']['data'] = $dados[$i] = ($read->getResult() ? $read->getResult()[0] : []);
                $dados[$i]['db_action'] = $action;
            } else {
                $data['data']['error'] += 1;
            }
        }
    }

    $json = new Json();
    $hist = $json->get("historic");
    $hist[$entity] = strtotime('now');
    $json->save("historic", $hist);

    //salva alterações
    if (!empty($dados)) {
        //se tiver mais que 50 resultados, deleta os acima de 50
        if (count($total = Helper::listFolder(PATH_HOME . "_cdn/update/{$entity}")) > 99) {
            $excluir = 101 - count($total);
            for ($i = 0; $i < $excluir; $i++) {
                if (isset($total[$i])) {
                    unlink(PATH_HOME . "_cdn/update/{$entity}/{$total[$i]}");
                } else {
                    break;
                }
            }
        }
        $store = new Json("update/{$entity}");
        $store->setVersionamento(false);
        $store->save($hist[$entity], $dados);
    }

    $data['data']['historic'] = $hist[$entity];
}