<?php

use \Helpers\Helper;
use \Entity\Metadados;
use \Entity\Json;
use \Conn\Read;
use \Config\Config;
use \Conn\SqlCommand;

$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$id = is_numeric($id) && $id > 0 ? (int) $id : null;
$search = filter_input(INPUT_POST, 'search', FILTER_DEFAULT);
$filter = filter_input(INPUT_POST, 'filter', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
$order = filter_input(INPUT_POST, 'order', FILTER_DEFAULT);
$reverse = filter_input(INPUT_POST, 'reverse', FILTER_VALIDATE_BOOLEAN);
$limit = filter_input(INPUT_POST, 'limit', FILTER_VALIDATE_INT);
$limit = empty($limit) ? 999999999 : $limit;
$offset = filter_input(INPUT_POST, 'offset', FILTER_VALIDATE_INT);

$historicFront = filter_input(INPUT_POST, 'historic', FILTER_DEFAULT);
$historicFrontTime = (int) (!empty($historicFront) ? explode("-", $historicFront)[0] : 0 );
$setor = !empty($_SESSION['userlogin']) ? $_SESSION['userlogin']['setor'] : "0";
$permissoes = Config::getPermission();
$json = new Json();
$hist = $json->get("historic");
$data['data'] = ['historic' => 0];

/**
 * Estou logado, não sou ADM, não tenho permissão de leitura, mas a entidade é o meu tipo de usuário
 */
$entityIsMySetor = ($setor !== "admin" && (isset($permissoes[$setor][$entity]['read']) && !$permissoes[$setor][$entity]['read']) && $setor !== "0" && $entity === $setor);

if ($setor === "admin" || (isset($permissoes[$setor][$entity]['read']) || $permissoes[$setor][$entity]['read']) || $entityIsMySetor) {

    //preenche caso não tenha nada de informação
    if (empty($hist[$entity])) {
        $hist[$entity] = strtotime('now') . "-" . rand(1000000, 9999999);
        $json->save("historic", $hist);
    }

    $histTime = (int) explode("-", $hist[$entity])[0];

    //verifica se há alterações nessa entidade que não forão recebidas pelo app, caso tenha, atualiza os dados
    if (empty($historicFront) || ($historicFrontTime < $histTime && !file_exists(PATH_HOME . "_cdn/update/{$entity}/{$historicFront}.json"))) {
        //download all data

        /**
         * @param array $filter
         * @param $dicionario
         * @return string
         */
        function exeReadApplyFilter(array $filter, $dicionario) {
            $where = [];
            foreach ($filter as $i => $filterOption) {
                if ($filterOption['operator'] === "por") {
                    foreach ($dicionario as $meta) {
                        if(!in_array($meta['key'], ["information", "identifier"]))
                            $where[$i][] = $meta['column'] . " LIKE '%{$filterOption['value']}%'";
                    }

                } else {
                    switch ($filterOption['operator']) {
                        case 'contém':
                            $where[$i][] = "{$filterOption['column']} LIKE '%{$filterOption['value']}%'";
                            break;
                        case 'igual a':
                            if(empty($filterOption['value']))
                                $where[$i][] = "{$filterOption['column']} IS NULL || {$filterOption['column']} = ''";
                            else
                                $where[$i][] = "{$filterOption['column']} = '{$filterOption['value']}'";

                            break;
                        case 'diferente de':
                            if(empty($filterOption['value']))
                                $where[$i][] = "{$filterOption['column']} NOT IS NULL && {$filterOption['column']} != ''";
                            else
                                $where[$i][] = "{$filterOption['column']} != '{$filterOption['value']}'";

                            break;
                        case 'começa com':
                            $where[$i][] = "{$filterOption['column']} LIKE '{$filterOption['value']}%'";
                            break;
                        case 'termina com':
                            $where[$i][] = "{$filterOption['column']} LIKE '%{$filterOption['value']}'";
                            break;
                        case 'maior que':
                            $where[$i][] = "{$filterOption['column']} > {$filterOption['value']}";
                            break;
                        case 'menor que':
                            $where[$i][] = "{$filterOption['column']} < {$filterOption['value']}";
                            break;
                        case 'maior igual a':
                            $where[$i][] = "{$filterOption['column']} >= {$filterOption['value']}";
                            break;
                        case 'menor igual a':
                            $where[$i][] = "{$filterOption['column']} <= {$filterOption['value']}";
                            break;
                        case 'não contém':
                            $where[$i][] = "{$filterOption['column']} NOT LIKE '%{$filterOption['value']}%'";
                            break;
                        case 'não começa com':
                            $where[$i][] = "{$filterOption['column']} NOT LIKE '{$filterOption['value']}%'";
                            break;
                        case 'não termina com':
                            $where[$i][] = "{$filterOption['column']} NOT LIKE '%{$filterOption['value']}'";
                    }
                }
            }

            $result = "";
            foreach ($where as $andContainer) {
                $result .= " && (";
                foreach ($andContainer as $e => $or)
                    $result .= ($e > 0 ? " || " : "") . $or;

                $result .= ")";
            }

            return $result;
        }

        $dicionario = Metadados::getDicionario($entity);
        $info = Metadados::getInfo($entity);
        $where = ($id ? "WHERE id = {$id}" : "WHERE id > 0");

        // Verifica se existe um vinculo deste usuário com o conteúdo, se tiver busca também
        if(!empty($setor) && $setor !== "admin" && $setor !== "0") {
            $metadados = Metadados::getDicionario($entity);

            $count = 0;
            foreach ($metadados as $col => $meta) {
                if($meta['format'] === "list" && $meta['relation'] === $setor) {
                    if($count === 0)
                        $where .= " && ({$meta['column']} = " . $_SESSION['userlogin']['setorData']['id'];
                    else
                        $where .= " || {$meta['column']} = " . $_SESSION['userlogin']['setorData']['id'];
                    $count++;
                }
            }

            if($count > 0)
                $where .= ")";
        }

        //Verifica se é multitenancy, se for, adiciona cláusula para buscar somente os dados referentes ao usuário
        if($where === "WHERE id > 0" && $setor !== "admin" && $setor !== "0" && !empty($info['autor']) && $info['autor'] === 2)
            $where .= " && ownerpub = " . $_SESSION['userlogin']['id'];

        $filterResult = "";
        if(!empty($filter))
            $filterResult = exeReadApplyFilter($filter, $dicionario);

        $where .= $filterResult;

        if(!empty($search)) {
            $searchWhere = "";
            foreach ($dicionario as $meta) {
                if(!in_array($meta['key'], ["information", "identifier"]))
                    $searchWhere .= ($searchWhere === "" ? "" : " || ") . $meta['column'] . " LIKE '%{$search}%'";
            }
            $where .= " && (" . $searchWhere . ")";
        }

        /**
         * Se não tiver permissão, mas for meus dados, permite
         */
        if($entityIsMySetor)
            $where .= " && id = " . $_SESSION['userlogin']['setorData']['id'];

        $where .= " ORDER BY " . (!empty($order) ? $order : "id") . ($reverse === null || $reverse ? " DESC" : " ASC") . " LIMIT {$limit}" . (!empty($offset) && $offset > -1 ? " OFFSET " . ($offset + 1) : "");

        $read = new Read();
        $read->exeRead($entity, $where);
        $results = $read->getResult() ?? [];
        if(!empty($results)) {
            //obtém nome da coluna da senha
            if(!empty($info['password'])) {
                foreach ($dicionario as $idDicionario => $item) {
                    if($idDicionario == $info['password']) {
                        $columnPassword = $item['column'];
                        break;
                    }
                }
            }

            foreach ($results as $i => $result) {
                $results[$i]['db_action'] = "create";

                //se tiver senha, exclui valor
                if(isset($columnPassword))
                    $results[$i][$columnPassword] = null;
            }
        }

        if($id) {
            $data['data']['total'] = 1;
        }  else {
            $sql = new SqlCommand();
            $sql->exeCommand("SELECT count(id) AS total FROM " . PRE . $entity . " WHERE id > 0" . $filterResult);
            $data['data']['total'] = $sql->getResult() && !empty($sql->getResult()[0]['total']) ? (int) $sql->getResult()[0]['total'] : 0;

        }
        $data['data']['data'] = $results;
        $data['data']['tipo'] = 1;
        $data['data']['historic'] = $hist[$entity];

    } elseif (!$entityIsMySetor && $historicFrontTime < $histTime) {

        /**
         * Verifica se tem colunas relacionadas ao meu usuário, ao qual vinculam o registro ao meu usuário
         * se tiver, considera como registro meu
         */
        $relationSetor = !1;
        $relationSetorColumn = "";
        if(!empty($setor) && $setor !== "admin" && $setor !== "0" && !empty($_SESSION['userlogin']['setorData']['id'])) {
            $metadados = Metadados::getDicionario($entity);
            foreach ($metadados as $col => $meta) {
                if($meta['format'] === "list" && $meta['relation'] === $setor) {
                    $relationSetor = !0;
                    $relationSetorColumn = $meta['column'];
                    break;
                }
            }
        }

        //download updates
        $data['data']['data'] = [];
        $info = Metadados::getInfo($entity);
        foreach (Helper::listFolder(PATH_HOME . "_cdn/update/{$entity}") as $update) {
            $historicUpdate = str_replace('.json', '', $update);
            $historicUpdateTime = (int) explode("-", $historicUpdate)[0];
            if ($historicFrontTime < $historicUpdateTime) {
                $dadosUp = json_decode(file_get_contents(PATH_HOME . "_cdn/update/{$entity}/{$update}"), !0);
                if(!empty($dadosUp) && ($setor === "admin" || empty($info['autor']) || ($relationSetor && $dadosUp[$relationSetorColumn] === $_SESSION['userlogin']['setorData']['id']) || ($info['autor'] === 2 && $dadosUp['ownerpub'] === $_SESSION['userlogin']['id'])))
                    $data['data']['data'][] = $dadosUp;
            }
        }

        $data['data']['tipo'] = 2;
        $data['data']['historic'] = $hist[$entity];
    }
}