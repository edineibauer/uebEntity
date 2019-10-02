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
$filter = filter_input(INPUT_POST, 'filter', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
$order = filter_input(INPUT_POST, 'order', FILTER_DEFAULT);
$reverse = filter_input(INPUT_POST, 'reverse', FILTER_VALIDATE_BOOLEAN);
$limit = filter_input(INPUT_POST, 'limit', FILTER_VALIDATE_INT);
$limit = !empty($limit) && $limit > 0 ? $limit : (int) LIMITOFFLINE;
$offset = filter_input(INPUT_POST, 'offset', FILTER_VALIDATE_INT);

$historicFront = filter_input(INPUT_POST, 'historic', FILTER_DEFAULT);
$historicFrontTime = (int) (!empty($historicFront) ? explode("-", $historicFront)[0] : 0 );
$setor = !empty($_SESSION['userlogin']) ? $_SESSION['userlogin']['setor'] : "0";
$permissoes = Config::getPermission();
$json = new Json();
$hist = $json->get("historic");
$data['data'] = ['historic' => 0];

if ($setor === "admin" || (isset($permissoes[$setor][$entity]['read']) || $permissoes[$setor][$entity]['read'])) {

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
         * @param string $entity
         * @param array $filter
         * @return string
         */
        function exeReadApplyFilter(string $entity, array $filter) {
            $dicionario = Metadados::getDicionario($entity);
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
                            $where[$i][] = "{$filterOption['column']} = '{$filterOption['value']}'";
                            break;
                        case 'diferente de':
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

        $where = ($id ? "WHERE id = {$id}" : "WHERE id > 0");

        // Verifica se existe um vinculo deste usuário com o conteúdo, se tiver busca também
        if(!empty($setor) && $setor !== "admin" && $setor !== "0") {
            $metadados = Metadados::getDicionario($entity);
            foreach ($metadados as $col => $meta) {
                if($meta['format'] === "list" && $meta['relation'] === $setor)
                    $where .= " && {$meta['column']} = " . $_SESSION['userlogin']['setorData']['id'];
            }
        }

        //Verifica se é multitenancy, se for, adiciona cláusula para buscar somente os dados referentes ao usuário
        if($where === "WHERE id > 0") {
            $info = Metadados::getInfo($entity);
            if ($setor !== "admin" && !empty($info['autor']) && $info['autor'] === 2)
                $where .= " && ownerpub = " . $_SESSION['userlogin']['id'];

        }

        $filterResult = "";
        if(!empty($filter))
            $filterResult = exeReadApplyFilter($entity, $filter);
        $where .= $filterResult;

        $where .= " ORDER BY " . (!empty($order) ? $order : "id") . ($reverse === null || $reverse ? " DESC" : " ASC") . " LIMIT {$limit}" . (!empty($offset) && $offset > -1 ? " OFFSET " . ($offset + 1) : "");

        $read = new Read();
        $read->exeRead($entity, $where);
        $results = $read->getResult() ?? [];
        if(!empty($results)) {
            foreach ($results as $i => $result) {
                $results[$i]['db_action'] = "create";
                if($entity === "usuarios")
                    $results[$i]['password'] = "";
            }
        }

        if($id) {
            $data['data']['total'] = 1;
        }  else {
            $sql = new SqlCommand();
            $sql->exeCommand("SELECT count(id) AS total FROM " . PRE . $entity . " WHERE id > 0" . $filterResult);
            $data['data']['total'] = $sql->getResult() && !empty($sql->getResult()[0]['total']) ? $sql->getResult()[0]['total'] : 0;

        }
        $data['data']['data'] = $results;
        $data['data']['tipo'] = 1;
        $data['data']['historic'] = $hist[$entity];

    } elseif ($historicFrontTime < $histTime) {
        //download updates
        $data['data']['data'] = [];
        foreach (Helper::listFolder(PATH_HOME . "_cdn/update/{$entity}") as $update) {
            $historicUpdate = str_replace('.json', '', $update);
            $historicUpdateTime = (int) explode("-", $historicUpdate)[0];
            if ($historicFrontTime < $historicUpdateTime) {
                $dadosUp = json_decode(file_get_contents(PATH_HOME . "_cdn/update/{$entity}/{$update}"), !0);
                if (!empty($dadosUp))
                    $data['data']['data'][] = $dadosUp;
            }
        }

        $data['data']['tipo'] = 2;
        $data['data']['historic'] = $hist[$entity];
    }
}