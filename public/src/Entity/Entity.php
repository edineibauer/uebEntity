<?php

namespace Entity;

use Conn\Create;
use Conn\Update;
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
     * Lê registros no servidor incluindo os dados relationados e decode dos JSON
     * relationData
     *
     * @param string $entity
     * @param null $id
     * @param false $ignoreSystem
     * @param false $ignoreOwner
     * @return array
     */
    public static function exeReadWithoutCache(string $entity, $id = null, $ignoreSystem = false, $ignoreOwner = false)
    {
        if(!Config::haveEntityPermission($entity, ["read"]))
            return [];

        $result = [];
        $info = Metadados::getInfo($entity);
        $dicionario = Metadados::getDicionario($entity);
        $selects = "";
        $command = "FROM " . $entity . " as e";
        $relations = [];
        $dicionarios = [];
        $infos = [];

        /**
         * Select the entity
         */
        if (!empty($info['columns_readable'])) {
            foreach ($info['columns_readable'] as $column)
                $selects .= ($selects === "" ? "" : ", ") . "e.{$column}";
        }

        /**
         * System id relation
         */
        if (!empty($info['system'])) {

            if (!isset($dicionarios[$info['system']]))
                $dicionarios[$info['system']] = Metadados::getDicionario($info['system']);

            if (!isset($infos[$info['system']]))
                $infos[$info['system']] = Metadados::getInfo($info['system']);

            if (!empty($infos[$info['system']]['columns_readable'])) {
                foreach ($infos[$info['system']]['columns_readable'] as $column)
                    $selects .= ", system_" . $info['system'] . ".{$column} as {$info['system']}___{$column}";
            }

            $command .= " LEFT JOIN " . $info['system'] . " as system_" . $info['system'] . " ON system_" . $info['system'] . ".id = e.system_id";
        }

        /**
         * Autorpub and Ownerpub id relation
         */
        if (!empty($info['autor'])) {

            if (!isset($dicionarios["usuarios"]))
                $dicionarios["usuarios"] = Metadados::getDicionario("usuarios");

            if (!isset($infos["usuarios"]))
                $infos["usuarios"] = Metadados::getInfo("usuarios");

            if (!empty($infos["usuarios"]['columns_readable'])) {
                foreach ($infos["usuarios"]['columns_readable'] as $column)
                    $selects .= ", autor_user.{$column} as autor_user___{$column}";
            }

            $command .= " LEFT JOIN usuarios as autor_user ON autor_user.id = e." . ($info['autor'] == 1 ? "autorpub" : "ownerpub");
        }

        /**
         * Include the data from each relation
         */
        if (!empty($info['relation'])) {
            foreach ($info['relation'] as $relationItem) {
                $relationEntity = $dicionario[$relationItem]['relation'];
                $relations[$dicionario[$relationItem]['column']] = $relationEntity;

                if (!isset($dicionarios[$relationEntity]))
                    $dicionarios[$relationEntity] = Metadados::getDicionario($relationEntity);

                if (!isset($infos[$relationEntity]))
                    $infos[$relationEntity] = Metadados::getInfo($relationEntity);

                if (!empty($infos[$relationEntity]['columns_readable'])) {
                    foreach ($infos[$relationEntity]['columns_readable'] as $column)
                        $selects .= ", data_" . $dicionario[$relationItem]['column'] . ".{$column} as {$dicionario[$relationItem]['relation']}___{$column}";
                }

                $command .= " LEFT JOIN " . $dicionario[$relationItem]['relation'] . " as data_" . $dicionario[$relationItem]['column'] . " ON data_" . $dicionario[$relationItem]['column'] . ".id = e." . $dicionario[$relationItem]['column'];
            }
        }

        /**
         * Set the limit result to return to front
         */
        $command = "SELECT " . $selects . " {$command}" . (is_numeric($id) && $id > 0 ? " WHERE e.id = {$id}" : "") . " ORDER BY id DESC LIMIT " . LIMITOFFLINE;

        /**
         * Execute the read command
         */
        $sql = new SqlCommand();
        $sql->exeCommand($command);

        /**
         * Convert join values into a array of relation data
         * Convert json values into array
         */
        if (empty($sql->getErro()) && !empty($sql->getResult())) {
            foreach ($sql->getResult() as $i => $register) {
                /**
                 * Work on a variable with the data of relationData
                 */
                $relationData = [];

                /**
                 * convert data from default format
                 */
                foreach ($dicionario as $meta) {
                    if(isset($register[$meta['column']])) {
                        $m = new \Entity\Meta($meta);
                        $m->setValue($register[$meta['column']]);
                        $register[$meta['column']] = $m->getValue();
                    } else {
                        $register[$meta['column']] = "";
                    }
                }
                if(!empty($register['id']))
                    $register['id'] = (int) $register['id'];

                /**
                 * Foreach register, check if have relationData to split
                 */
                foreach ($register as $column => $value) {

                    /**
                     * Check System ID relation
                     */
                    if (!empty($info['system']) && strpos($column, $info['system'] . '___') !== false) {

                        /**
                         * Add item to a relation register system_id
                         */
                        $relationData["system_id"][str_replace($info['system'] . "___", "", $column)] = $value;

                        /**
                         * Remove item from base register
                         */
                        unset($register[$column]);
                    }

                    /**
                     * Autorpub and Ownerpub id relation
                     */
                    if (!empty($info['autor']) && strpos($column, 'autor_user___') !== false) {

                        /**
                         * Add item to a relation register
                         */
                        $relationData["usuarios"][str_replace("autor_user___", "", $column)] = $value;

                        /**
                         * Remove item from base register
                         */
                        unset($register[$column]);
                    }

                    /**
                     * If have relation data together in the base register
                     */
                    if (!empty($relations)) {
                        foreach ($relations as $RelationColumn => $relation) {
                            if (strpos($column, $relation . '___') !== false) {

                                /**
                                 * Add item to a relation register
                                 */
                                $relationData[$RelationColumn][str_replace($relation . "___", "", $column)] = $value;

                                /**
                                 * Remove item from base register
                                 */
                                unset($register[$column]);
                            }
                        }
                    }
                }

                if (!empty($info['system'])) {
                    /**
                     * Check if the struct of relation data received have a ID
                     * if not, so delete
                     */
                    if (empty($relationData["system_id"]['id'])) {
                        unset($relationData["system_id"]);

                    } else {

                        /**
                         * Decode all json on base relation register
                         */
                        foreach ($dicionarios[$info['system']] as $meta) {
                            if(isset($relationData["system_id"][$meta['column']])) {
                                $m = new \Entity\Meta($meta);
                                $m->setValue($relationData["system_id"][$meta['column']]);
                                $relationData["system_id"][$meta['column']] = $m->getValue();
                            } else {
                                $relationData["system_id"][$meta['column']] = "";
                            }
                        }
                        if(!empty($relationData["system_id"]['id']))
                            $relationData["system_id"]['id'] = (int) $relationData["system_id"]['id'];
                    }
                }

                if (!empty($info['autor'])) {
                    /**
                     * Check if the struct of relation data received have a ID
                     * if not, so delete
                     */
                    if (empty($relationData["usuarios"]['id']))
                        unset($relationData["usuarios"]);


                    /**
                     * Decode all json on base relation register
                     */
                    foreach ($dicionarios["usuarios"] as $meta) {
                        if(!empty($relationData["usuarios"][$meta['column']])) {
                            $m = new \Entity\Meta($meta);
                            $m->setValue($relationData["usuarios"][$meta['column']]);
                            $relationData["usuarios"][$meta['column']] = $m->getValue();
                        } else {
                            $relationData["usuarios"][$meta['column']] = "";
                        }
                    }

                    if(!empty($relationData["usuarios"]['id']))
                        $relationData["usuarios"]['id'] = (int) $relationData["usuarios"]['id'];

                    $relationData[$info['autor'] == 1 ? "autorpub" : "ownerpub"] = $relationData["usuarios"];
                    unset($relationData["usuarios"]);
                }

                /**
                 * After separate the base data from the relation data
                 * check if the relation data have a ID an decode json
                 */
                if (!empty($relations)) {
                    foreach ($relations as $RelationColumn => $relation) {

                        /**
                         * Check if the struct of relation data received have a ID
                         * if not, so delete
                         */
                        if (empty($relationData[$RelationColumn]['id'])) {
                            unset($relationData[$RelationColumn]);

                        } else {

                            /**
                             * Decode all json on base relation register
                             */
                            foreach ($dicionarios[$relation] as $meta) {
                                if(isset($relationData[$RelationColumn][$meta['column']])) {
                                    $m = new \Entity\Meta($meta);
                                    $m->setValue($relationData[$RelationColumn][$meta['column']]);
                                    $relationData[$RelationColumn][$meta['column']] = $m->getValue();
                                } else {
                                    $relationData[$RelationColumn][$meta['column']] = "";
                                }
                            }
                            if(!empty($relationData[$RelationColumn]['id']))
                                $relationData[$RelationColumn]['id'] = (int) $relationData[$RelationColumn]['id'];

                            /**
                             * If is a user relation entity add the relationData
                             */
                            foreach ($dicionario as $meta) {
                                if($meta['column'] === $RelationColumn && $meta['relation'] === "usuarios" && !empty($relationData[$RelationColumn]['setor'])) {
                                    $relationData[$RelationColumn]['relationData'][$relationData[$RelationColumn]['setor']] = self::getUserSetorData($relationData[$RelationColumn]['setor'], $relationData[$RelationColumn]['id']);
                                    break;
                                }
                            }
                        }
                    }
                }

                $register["relationData"] = $relationData;
                $result[] = $register;
            }

            /**
             * if is user database, include the setor data relation
             * Or if have Autorpub or Ownerpub, so include the setor data relation
             */
            if ($entity === "usuarios" || !empty($info['autor'])) {
                foreach ($result as $i => $item) {
                    $entitySetor = ($entity === "usuarios" ? $item['setor'] : ($info['autor'] == 1 ? $item['relationData']["autorpub"]['setor'] ?? "" : $item['relationData']["ownerpub"]['setor'] ?? ""));
                    if (!empty($entitySetor)) {
                        $idUsuario = ($entity === "usuarios" ? $item['id'] : ($info['autor'] == 1 ? $item['relationData']["autorpub"]['id'] : $item['relationData']["ownerpub"]['id']));

                        if ($entity === "usuarios")
                            $result[$i]['relationData'][$entitySetor] = self::getUserSetorData($entitySetor, $idUsuario);
                        else
                            $result[$i]['relationData'][($info['autor'] == 1 ? "autorpub" : "ownerpub")]["relationData"][$entitySetor] = self::getUserSetorData($entitySetor, $idUsuario);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Lê registros no servidor incluindo os dados relationados e decode dos JSON
     * usa cache para acelerar o retorno da busca
     * relationData
     *
     * @param string $entity
     * @param null $id
     * @param false $ignoreSystem
     * @param false $ignoreOwner
     * @return array
     */
    public static function exeRead(string $entity, $id = null, $ignoreSystem = false, $ignoreOwner = false)
    {
        $results = [];

        /**
         * É necessário verificar permissões
         * System_id, autor, setor
         */
        $results[] = self::exeReadWithoutCache($entity, (is_numeric($id) && $id > 0 ? $id : null), $ignoreSystem, $ignoreOwner)[0];

        return $results;
    }

    /**
     * @param string $entity
     * @param int $id
     * @return array
     */
    public static function getUserSetorData(string $entity, int $id): array
    {
        $read = new \Conn\Read();
        $info = Metadados::getInfo($entity);
        $dicionario = Metadados::getDicionario($entity);
        $result = [];

        if (!empty($info['columns_readable']))
            $read->setSelect($info['columns_readable']);

        $read->exeRead($entity, "WHERE usuarios_id = :id", ["id" => $id]);
        if ($read->getResult()) {
            /**
             * Decode all json on base relation register
             */
            $result = $read->getResult()[0];
            foreach ($dicionario as $meta) {
                if(isset($result[$meta['column']])) {
                    $m = new \Entity\Meta($meta);
                    $m->setValue($result[$meta['column']]);
                    $result[$meta['column']] = $m->getValue();
                } else {
                    $result[$meta['column']] = "";
                }
            }
            if(!empty($result['id']))
                $result['id'] = (int) $result['id'];
        }

        return $result;
    }

    /**
     * Create new entity data
     *
     * @param string $entity
     * @param array $data
     * @param bool $save
     * @return mixed
     */
    public static function create(string $entity, array $data)
    {
        return self::exeCreate($entity, $data, !0);
    }

    /**
     * Update entity data
     *
     * @param string $entity
     * @param array $data
     * @param bool $save
     * @return mixed
     */
    public static function update(string $entity, array $data)
    {
        return self::exeCreate($entity, $data, !0);
    }

    /**
     * Validate entity data
     *
     * @param string $entity
     * @param array $data
     * @param bool $save
     * @return mixed
     */
    public static function validateData(string $entity, array $data)
    {
        return self::exeCreate($entity, $data, !1);
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
     * @param string|null $entity
     * @param bool $keepId
     * @param array $permissions
     * @return array
     */
    public static function dicionario(string $entity = null, bool $keepId = !1, array $permissions = []): array
    {
        $list = [];
        if (empty($entity)) {
            $setor = Config::getSetor();

            if(empty($permissions)) {
                $permissions = Config::minifyPermissions(Config::getPermission($setor));
                if ($setor === "admin") {
                    foreach ($permissions as $entity => $dadosP)
                        $permissions[$entity] = 1;
                } else {
                    foreach ($permissions as $entity => $dadosP) {
                        if ($entity === $setor)
                            $permissions[$entity] = 1;
                        elseif (is_array($dadosP))
                            $permissions[$entity] = $dadosP[1] === 1 || $dadosP[2] === 1 || $dadosP[3] === 1 ? 1 : 0;
                    }
                }
            }

            //read all dicionarios
            foreach (Helper::listFolder(PATH_HOME . "entity/cache") as $entity) {
                if (pathinfo($entity, PATHINFO_EXTENSION) === "json") {

                    $entidade = str_replace(".json", "", $entity);

                    if (isset($permissions[$entidade]) && $permissions[$entidade]) {
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
     *  Verifica dicionários info permitidos e retorna
     *
     * @param string|null $entity
     * @param array $permissions
     * @return array
     */
    public static function info(string $entity = null, array $permissions = []): array
    {
        $list = [];
        if (empty($entity)) {

            if(empty($permissions)) {
                $permissions = Config::minifyPermissions(Config::getPermission($setor));
                $setor = Config::getSetor();
                if($setor === "admin") {
                    foreach ($permissions as $entity => $dadosP)
                        $permissions[$entity] = 1;
                } else {
                    foreach ($permissions as $entity => $dadosP) {
                        if($entity === $setor)
                            $permissions[$entity] = 1;
                        elseif(is_array($dadosP))
                            $permissions[$entity] = $dadosP[1] === 1 || $dadosP[2] === 1 || $dadosP[3] === 1 ? 1 : 0;
                    }
                }
            }

            //read all info dicionarios
            foreach (Helper::listFolder(PATH_HOME . "entity/cache/info") as $entity) {
                if (pathinfo($entity, PATHINFO_EXTENSION) === "json") {
                    $entidade = str_replace(".json", "", $entity);
                    if (isset($permissions[$entidade]) && $permissions[$entidade])
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
            $read->exeRead($entity, "WHERE id = :id", ["id" => $id]);
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
                                    $tableRelational = $entityRelation . "_" . $entity . "_" . $column;

                                    $read = new Read();
                                    $read->exeRead($entityRelation, "WHERE {$userColumn} = :user", ["user" => $_SESSION['userlogin']['id']]);
                                    if ($read->getResult()) {
                                        $idUser = $read->getResult()[0]['id'];
                                        $read->exeRead($tableRelational, "WHERE {$entityRelation}_id = :id", ["id" => $idUser]);
                                        if ($read->getResult())
                                            $continua = false;
                                    }
                                }
                            }

                            if ($continua) {
                                $read->exeRead("usuarios", "WHERE id = :id", ["id" => $idData]);
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

                    $read->exeRead("usuarios", "WHERE id = :idl", ["idl" => $tableData[$metadados[$dicionario->getInfo()['publisher']]['column']]]);
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
}