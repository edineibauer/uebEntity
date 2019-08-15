<?php

use Entity\Json;
use \Helpers\Helper;

$entity = strip_tags(trim(filter_input(INPUT_POST, 'entity', FILTER_DEFAULT)));
$dados = filter_input(INPUT_POST, 'dados', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
$data['data'] = ['error' => 0, 'data' => [], 'historic' => 0];

if (!empty($entity) && file_exists(PATH_HOME . "entity/cache/{$entity}.json") && !empty($dados) && is_array($dados)) {
    $read = new \Conn\Read();
    $del = new \Conn\Delete();

    foreach ($dados as $i => $dado) {
        $action = $dado['db_action'];
        if ($action === "delete") {
            $where = "";
            if (is_array($dado['id'])) {
                foreach ($dado['id'] as $item)
                    $where .= (empty($where) ? "WHERE id = {$item}" : " || id = {$item}");

            } elseif (is_numeric($dado['id'])) {
                $where = "WHERE id = {$dado['id']}";
            }

            $del->exeDelete($entity, $where);

            $dado['id_old'] = $dado['id'];
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

                $store = new Json("error/{$entity}");
                $store->setVersionamento(!1);
                $store->save(strtotime('now'), $registro);

                $data['data']['data'][] = [];
                $data['data']['error'] += 1;
            }
        }
    }

    $data['data']['historic'] = strtotime('now');
}
