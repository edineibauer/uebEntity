<?php

namespace Entity;

use \Helpers\Helper;
use \Conn\Read;
use \Config\Config;
use \Conn\SqlCommand;

class Entity extends EntityCreate
{
    /**
     * Le a data de uma entidade de forma extendida
     *
     * @param string $entity
     * @param mixed $data
     * @param bool $recursive
     * @return mixed
     */
    public static function read(string $entity, $data = null)
    {
        return self::exeRead($entity, $data);
    }

    /**
     * Salva data à uma entidade
     *
     * @param string $entity
     * @param array $data
     * @param bool $save
     * @param mixed $callback
     * @return mixed
     */
    public static function add(string $entity, array $data, bool $save = true)
    {
        return self::exeCreate($entity, $data, $save);
    }

    /**
     * Deleta informações de uma entidade
     *
     * @param string $entity
     * @param mixed $data
     * @param bool $checkPermission
     */
    public static function delete(string $entity, $data, bool $checkPermission = true)
    {
        self::exeDelete($entity, $data, $checkPermission);
    }

    /**
     * @param string $entity
     * @param array $filter
     * @param string|null $order
     * @param bool $reverse
     * @param int $limit
     * @param int $offset
     * @param string|null $historicFront
     * @return mixed
     */
    public static function loadData(string $entity, array $filter = [], string $order = null, bool $reverse = false, int $limit = 1000, int $offset = -1, string $historicFront = null)
    {
        $limit = !empty($limit) && $limit > 0 ? $limit : (int)LIMITOFFLINE;
        $historicFrontTime = (!empty($historicFront) ? (int)explode("-", $historicFront)[0] : 0);
        $setor = (!empty($_SESSION['userlogin']) ? $_SESSION['userlogin']['setor'] : "0");
        $permissoes = Config::getPermission();
        $json = new Json();
        $hist = $json->get("historic");
        $result = ['historic' => 0];

        /**
         * Estou logado, não sou ADM, não tenho permissão de leitura, mas a entidade é o meu tipo de usuário
         */
        $entityIsMySetor = ($setor !== "admin" && (isset($permissoes[$setor][$entity]['read']) && !$permissoes[$setor][$entity]['read']) && $setor !== "0" && $entity === $setor);

        if ($setor === "admin" || (isset($permissoes[$setor]) && isset($permissoes[$setor][$entity]) && isset($permissoes[$setor][$entity]['read']) && $permissoes[$setor][$entity]['read']) || $entityIsMySetor) {

            //preenche caso não tenha nada de informação
            if (empty($hist[$entity])) {
                $hist[$entity] = strtotime('now') . "-" . rand(1000000, 9999999);
                $json->save("historic", $hist);
            }

            $histTime = (int)explode("-", $hist[$entity])[0];

            //verifica se há alterações nessa entidade que não forão recebidas pelo app, caso tenha, atualiza os dados
            if (empty($historicFront) || ($historicFrontTime < $histTime && !file_exists(PATH_HOME . "_cdn/update/{$entity}/{$historicFront}.json"))) {
                //download all data


                $dicionario = Metadados::getDicionario($entity);
                $info = Metadados::getInfo($entity);
                $where = "WHERE id > 0";

                // Verifica se existe um vinculo deste usuário com o conteúdo, se tiver busca também
                if (!empty($setor) && $setor !== "admin" && $setor !== "0") {
                    $metadados = Metadados::getDicionario($entity);

                    $count = 0;
                    foreach ($metadados as $col => $meta) {
                        if ($meta['format'] === "list" && $meta['relation'] === $setor) {
                            if ($count === 0)
                                $where .= " && ({$meta['column']} = " . $_SESSION['userlogin']['setorData']['id'];
                            else
                                $where .= " || {$meta['column']} = " . $_SESSION['userlogin']['setorData']['id'];
                            $count++;
                        }
                    }

                    if ($count > 0)
                        $where .= ")";
                }

                //Verifica se é multitenancy, se for, adiciona cláusula para buscar somente os dados referentes ao usuário
                if ($where === "WHERE id > 0" && $setor !== "admin" && $setor !== "0" && !empty($info['autor']) && $info['autor'] === 2)
                    $where .= " && ownerpub = " . $_SESSION['userlogin']['id'];

                $filterResult = "";
                if (!empty($filter))
                    $filterResult = self::exeReadApplyFilter($filter, $dicionario);
                $where .= $filterResult;

                /**
                 * Se não tiver permissão, mas for meus dados, permite
                 */
                if ($entityIsMySetor)
                    $where .= " && id = " . $_SESSION['userlogin']['setorData']['id'];

                $where .= " ORDER BY " . (!empty($order) ? $order : "id") . ($reverse === null || $reverse ? " DESC" : " ASC") . " LIMIT {$limit}" . (!empty($offset) && $offset > -1 ? " OFFSET " . ($offset + 1) : "");

                $read = new Read();
                $read->exeRead($entity, $where);
                $results = $read->getResult() ?? [];
                if (!empty($results)) {
                    //obtém nome da coluna da senha
                    if (!empty($info['password'])) {
                        foreach ($dicionario as $idDicionario => $item) {
                            if ($idDicionario == $info['password']) {
                                $columnPassword = $item['column'];
                                break;
                            }
                        }
                    }

                    foreach ($results as $i => $resultados) {
                        $results[$i]['db_action'] = "create";

                        //se tiver senha, exclui valor
                        if (isset($columnPassword))
                            $results[$i][$columnPassword] = null;
                    }
                }

                $sql = new SqlCommand();
                $sql->exeCommand("SELECT count(id) AS total FROM " . PRE . $entity . " WHERE id > 0" . $filterResult);
                $result['total'] = $sql->getResult() && !empty($sql->getResult()[0]['total']) ? (int)$sql->getResult()[0]['total'] : 0;
                $result['data'] = $results;
                $result['tipo'] = 1;
                $result['historic'] = $hist[$entity];

            } elseif (!$entityIsMySetor && $historicFrontTime < $histTime) {

                /**
                 * Verifica se tem colunas relacionadas ao meu usuário, ao qual vinculam o registro ao meu usuário
                 * se tiver, considera como registro meu
                 */
                $relationSetor = !1;
                $relationSetorColumn = "";
                if (!empty($setor) && $setor !== "admin" && $setor !== "0" && !empty($_SESSION['userlogin']['setorData']['id'])) {
                    $metadados = Metadados::getDicionario($entity);
                    foreach ($metadados as $col => $meta) {
                        if ($meta['format'] === "list" && $meta['relation'] === $setor) {
                            $relationSetor = !0;
                            $relationSetorColumn = $meta['column'];
                            break;
                        }
                    }
                }

                //download updates
                $result['data'] = [];
                $info = Metadados::getInfo($entity);
                foreach (Helper::listFolder(PATH_HOME . "_cdn/update/{$entity}") as $update) {
                    $historicUpdate = str_replace('.json', '', $update);
                    $historicUpdateTime = (int)explode("-", $historicUpdate)[0];
                    if ($historicFrontTime < $historicUpdateTime) {
                        $dadosUp = json_decode(file_get_contents(PATH_HOME . "_cdn/update/{$entity}/{$update}"), !0);
                        if (!empty($dadosUp) && ($setor === "admin" || empty($info['autor']) || ($relationSetor && $dadosUp[$relationSetorColumn] === $_SESSION['userlogin']['setorData']['id']) || ($info['autor'] === 2 && $dadosUp['ownerpub'] === $_SESSION['userlogin']['id'])))
                            $result['data'][] = $dadosUp;
                    }
                }

                $result['tipo'] = 2;
                $result['historic'] = $hist[$entity];
            }
        }

        return $result;
    }

    /**
     * Deleta informações de uma entidade
     *
     * @param string $entity
     * @param mixed $data
     * @param bool $checkPermission
     * @return mixed
     */
    public static function copy(string $entity, $data, bool $checkPermission = true)
    {
        return self::exeCopy($entity, $data, $checkPermission);
    }

    /**
     * Verifica dicionários permitidos e retorna
     *
     * @param string|null $entity
     * @param bool|false $keepId
     * @return array
     */
    public static function dicionario(string $entity = null, bool $keepId = !1): array
    {
        $list = [];
        if (empty($entity)) {

            //read all dicionarios
            foreach (Helper::listFolder(PATH_HOME . "entity/cache") as $entity) {
                if ($entity !== "info" && preg_match("/\.json$/i", $entity)) {

                    $entidade = str_replace(".json", "", $entity);

                    if (Config::haveEntityPermission($entidade)) {
                        $result = Metadados::getDicionario($entidade, $keepId, !0);
                        if (!empty($result)) {

                            /**
                             * Convert id key to column name
                             */
                            foreach ($result as $id => $metas) {
                                $metas['id'] = $id;
                                $list[$entidade][$metas['column']] = $metas;
                            }
                        }
                    }
                }
            }

        } elseif (file_exists(PATH_HOME . "entity/cache/{$entity}.json") && Config::haveEntityPermission($entity)) {

            $meta = Metadados::getDicionario($entity, !0, !0);
            if (!empty($meta)) {

                /**
                 * Convert id key to column name
                 */
                foreach ($meta as $id => $metas)
                    $list[$metas['column']] = $metas;
            }
        }

        return $list;
    }

    /**
     * Verifica dicionários info permitidos e retorna
     *
     * @param string|null $entity
     * @return array
     */
    public static function info(string $entity = null): array
    {
        $list = [];
        if (empty($entity)) {

            //read all info dicionarios
            foreach (Helper::listFolder(PATH_HOME . "entity/cache/info") as $entity) {
                if (preg_match("/\.json$/i", $entity)) {

                    $entidade = str_replace(".json", "", $entity);
                    if (Config::haveEntityPermission($entidade))
                        $list[$entidade] = Metadados::getInfo($entidade);
                }
            }

        } elseif (file_exists(PATH_HOME . "entity/cache/info/{$entity}.json") && Config::haveEntityPermission($entity)) {

            $list = Metadados::getInfo($entity);
        }

        return $list;
    }

    /**
     * @param string $entity
     * @param mixed $id
     * @param bool $check
     * @return bool
     */
    public static function checkPermission(string $entity, $id = null, bool $check = true): bool
    {
        return true;

        $login = $_SESSION['userlogin'] ?? null;
        $allowCreate = file_exists(PATH_HOME . "_config/entity_not_show.json") ? json_decode(file_get_contents(PATH_HOME . "_config/entity_not_show.json"), true) : [];

        //permissão master
        if (!empty($login['setor']) && $login['setor'] === 1 && $login['nivel'] === 1)
            return true;

        if (!$login) {
            //Anônimo tem permissão para criar caso não esteja na lista negra
            return (!$id && !empty($allowCreate) && !in_array($entity, $allowCreate[0]));

        } else {
            //Logado
            //Bloqueia Alterações ou Criação em entidades selecionadas para o setor do usuário
            if (!empty($allowCreate[$login['setor']]) && in_array($entity, $allowCreate[$login['setor']]) && $id)
                return false;

            $dicionario = new Dicionario($entity);
            $read = new Read();

            //check associação simples if have entity usuários ou if have publisher
            $read->exeRead($entity, "WHERE id = :id", "id={$id}");
            if ($read->getResult()) {
                $tableData = $read->getResult()[0];
                foreach ($dicionario->getAssociationSimple() as $meta) {
                    if ($meta->getRelation() === "usuarios" || $meta->getKey() === "publisher") {
                        $idData = $tableData[$meta->getColumn()];
                        if (!empty($idData) && $idData != $login['id']) {

                            $continua = true;
                            $general = json_decode(file_get_contents(PATH_HOME . "entity/general/general_info.json"), true);
                            if (!empty($general[$entity]['owner']) || !empty($general[$entity]['ownerPublisher'])) {
                                foreach (array_merge($general[$entity]['owner'] ?? [], $general[$entity]['ownerPublisher'] ?? []) as $item) {
                                    $entityRelation = $item[0];
                                    $column = $item[1];
                                    $userColumn = $item[2];
                                    $tableRelational = PRE . $entityRelation . "_" . $entity . "_" . $column;

                                    $read = new Read();
                                    $read->exeRead($entityRelation, "WHERE {$userColumn} = :user", "user={$_SESSION['userlogin']['id']}");
                                    if ($read->getResult()) {
                                        $idUser = $read->getResult()[0]['id'];
                                        $read->exeRead($tableRelational, "WHERE {$entityRelation}_id = :id", "id={$idUser}");
                                        if ($read->getResult())
                                            $continua = false;
                                    }
                                }
                            }

                            if ($continua) {
                                $read->exeRead("usuarios", "WHERE id = :id", "id={$idData}");
                                if ($read->getResult() && $login['setor'] >= $read->getResult()[0]['setor'])
                                    return false;
                            }
                        }
                    }
                }
            }

            //permite caso a verificação esteja desativada ou se for criação ou se a entidade não possui publisher
            if (!$check || !$id || ($entity !== "usuarios" && empty($dicionario->getInfo()['publisher'])))
                return true;

            if (isset($tableData)) {
                if ($entity !== "usuarios") {

                    $metadados = Metadados::getDicionario($entity);

                    if ($login['id'] == $tableData[$metadados[$dicionario->getInfo()['publisher']]['column']])
                        return true;

                    $read->exeRead("usuarios", "WHERE id = :idl", "idl={$tableData[$metadados[$dicionario->getInfo()['publisher']]['column']]}");
                    if ($read->getResult() && (($login['setor'] == $read->getResult()[0]['setor'] && $login['nivel'] < $read->getResult()[0]['nivel']) || $login['id'] === $read->getResult()[0]['id']))
                        return true;
                    else
                        return false;
                }

                if (($login['setor'] == $tableData['setor'] && $login['nivel'] < $tableData['nivel']) || $login['id'] === $tableData['id'] || $login['setor'] < $tableData['setor'])
                    return true;
            }

            return false;
        }
    }

    /**
     * @param array $filter
     * @param $dicionario
     * @return string
     */
    private static function exeReadApplyFilter(array $filter, $dicionario)
    {
        $where = [];
        foreach ($filter as $i => $filterOption) {

            /**
             * Converte valores caso esteja com os nomes em portugues
             */
            if (!isset($filterOption['operator']) && isset($filterOption['operador'])) {
                $filterOption = [
                    "column" => $filterOption['coluna'],
                    "operator" => $filterOption['operador'],
                    "value" => $filterOption['valor']
                ];
            }

            if ($filterOption['operator'] === "por") {
                foreach ($dicionario as $meta) {
                    if (!in_array($meta['key'], ["information", "identifier"]))
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
}