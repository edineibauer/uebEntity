<?php

use Entity\Dicionario;
use Entity\Json;
use Helpers\Check;
use \Helpers\Helper;

$entity = strip_tags(trim(filter_input(INPUT_POST, 'entity', FILTER_DEFAULT)));
$dados = filter_input(INPUT_POST, 'dados', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
$data['data'] = ['error' => 0, 'data' => [], 'historic' => 0];

if (!empty($entity) && file_exists(PATH_HOME . "entity/cache/{$entity}.json") && !empty($dados) && is_array($dados)) {
    $read = new \Conn\Read();
    $del = new \Conn\Delete();

    function deleteImages(array $data)
    {
        foreach ($data as $image) {
            if (!empty($image['url']) && file_exists(PATH_HOME . str_replace(HOME, '', $image['url'])))
                unlink(PATH_HOME . str_replace(HOME, '', $image['url']));

            if(!empty($image['urls']) && is_array($image['urls'])) {
                foreach ($image['urls'] as $url) {
                    if (file_exists(PATH_HOME . str_replace(HOME, '', $url)))
                        unlink(PATH_HOME . str_replace(HOME, '', $url));
                }
            }
        }
    }

    function checkEntityImageDelete(string $entity, array $data)
    {
        $dic = new Dicionario($entity);

        foreach ($dic->getDicionario() as $item) {
            if ($item->getKey() === "source" && !empty($data[$item->getColumn()])) {
                $imageData = [];
                if (Check::isJson($data[$item->getColumn()]))
                    $imageData = json_decode($data[$item->getColumn()], !0);
                elseif (is_array($data[$item->getColumn()]))
                    $imageData = $data[$item->getColumn()];

                if (!empty($imageData) && is_array($imageData))
                    deleteImages($imageData);
            } elseif ($item->getKey() === "relation" && $item->getFormat() !== "list" && !empty($data[$item->getColumn()])) {
                $dataRelation = [];
                if (Check::isJson($data[$item->getColumn()]))
                    $dataRelation = json_decode($data[$item->getColumn()], !0);
                elseif (is_array($data[$item->getColumn()]))
                    $dataRelation = $data[$item->getColumn()];

                if (!empty($dataRelation) && is_array($dataRelation)) {
                    foreach ($dataRelation as $json) {
                        if (!empty($json) && is_array($json))
                            checkEntityImageDelete($item->getRelation(), $json);
                    }
                }
            }
        }
    }

    foreach ($dados as $i => $dado) {
        $action = $dado['db_action'];
        if ($action === "delete") {

            if (is_numeric($dado['id'])) {
                $read->exeRead($entity, "WHERE id = {$dado['id']}");
                if ($read->getResult()) {
                    $result = $read->getResult()[0];

                    checkEntityImageDelete($entity, $result);

                    $del = new \Conn\Delete();
                    $del->exeDelete($entity, "WHERE id = {$dado['id']}");
                }
            } else {
                $data['data']['error'] += 1;
            }

            $dado['id_old'] = $dado['id'];
            $data['data']['data'][] = $dado;

        } else {

            $registro = $dado;
            unset($registro['db_action']);

            //remove id se existir e for criar
            $idOld = $registro['id'];

            if ($action === "create") {
                unset($registro['id']);

            } elseif ($action === "update") {
                $read->exeRead($entity, "WHERE id = :id", "id={$registro['id']}");
                if (!$read->getResult()) {
                    unset($registro['id']);
                    $action = "create";
                }
            }

            $id = \Entity\Entity::add($entity, $registro);

            if (is_numeric($id)) {
                $read->exeRead($entity, "WHERE id = :id", "id={$id}");
                $result = ($read->getResult() ? $read->getResult()[0] : []);
                $result['db_action'] = $action;
                $dados[$i] = $result;

                $dic = new \Entity\Dicionario($entity);
                foreach ($dic->getDicionario() as $i => $meta) {
                    if($meta->getFormat() === "password" || $meta->getFormat() === "passwordRequired") {
                        $result[$meta->getColumn()] = $meta->getFormat() === "password" ? $dado[$meta->getColumn()] ?? "" : "";
                        unset($dados[$meta->getColumn()]);
                    }
                }

                $result['id_old'] = $idOld;
                $data['data']['data'][] = $result;
            } else {

                $result = $registro;
                $result['db_error'] = $id;
                $result['id_old'] = $idOld;

                if(empty($id) || !is_array($id)) {
                    $store = new Json("error/{$entity}");
                    $store->setVersionamento(!1);
                    $store->save(strtotime('now'), $result);
                }

                $data['data']['data'][] = $result;
                $data['data']['error'] += 1;
            }
        }
    }

    $data['data']['historic'] = strtotime('now');
}
