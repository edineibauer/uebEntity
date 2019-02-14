<?php

use Entity\Json;
use \Helpers\Helper;

$entity = strip_tags(trim(filter_input(INPUT_POST, 'entity', FILTER_DEFAULT)));
$dados = filter_input(INPUT_POST, 'dados', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
$data['data'] = ['error' => 0, 'data' => [], 'historic' => 0];

if (!empty($entity) && file_exists(PATH_HOME . "entity/cache/{$entity}.json") && !empty($dados) && is_array($dados)) {
    $read = new \Conn\Read();
    $del = new \Conn\Delete();

    $delList = [];
    foreach ($dados as $i => $dado) {
        $action = $dado['db_action'];
        if ($action === "delete") {
            if (is_array($dado['delete'])) {
                foreach ($dado['delete'] as $item) {
                    $read->exeRead($entity, "WHERE id = :id", "id={$item}");
                    if($read->getResult()) {
                        $item = $read->getResult()[0];
                        $del->exeDelete($entity, "WHERE id = :id", "id={$item['id']}");
                        new \Entity\React("delete", $entity, $item, $item);
                    }
                    $delList[] = (int) $item;
                }
            } elseif (is_numeric($dado['delete'])) {
                $read->exeRead($entity, "WHERE id = :id", "id={$dado['delete']}");
                if($read->getResult()) {
                    $item = $read->getResult()[0];
                    $del->exeDelete($entity, "WHERE id = :id", "id={$item['id']}");
                    new \Entity\React("delete", $entity, $item, $item);
                }
                $delList[] = (int) $dado['delete'];
            }

            $dado['id_old'] = $dado['delete'];
            $data['data']['data'][] = $dado;
        } else {

            $registro = $dado;
            unset($registro['db_action']);

            //remove id se existir e for criar
            $idOld = $registro['id'];
            if ($action === "create")
                unset($registro['id']);

            $id = \Entity\Entity::add($entity, $registro);

            if (is_numeric($id)) {
                $read->exeRead($entity, "WHERE id = :id", "id={$id}");
                $result = ($read->getResult() ? $read->getResult()[0] : []);
                $result['db_action'] = $action;
                $dados[$i] = $result;

                $dic = new \Entity\Dicionario($entity);
                foreach ($dic->getDicionario() as $i => $meta) {
                    if($meta->getFormat() === "password" || $meta->getFormat() === "passwordRequired") {
                        $result[$meta->getColumn()] = $meta->getFormat() === "password" ? $dado[$meta->getColumn()] : "";
                        unset($dados[$meta->getColumn()]);
                    }
                }

                $result['id_old'] = $idOld;
                $data['data']['data'][] = $result;
            } else {
                $data['data']['data'][] = [];
                $data['data']['error'] += 1;
            }
        }
    }

    $data['data']['historic'] = strtotime('now');
}
