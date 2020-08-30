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
     * Lê registros no servidor incluindo os dados relationados e decode dos JSON
     * relationData
     * @param string $entity
     * @param null $id
     * @return array|mixed|null
     */
    public static function exeRead(string $entity, $id = null)
    {
        $info = Metadados::getInfo($entity);
        $dicionario = Metadados::getDicionario($entity);
        $selects = "";
        $command = "FROM " . PRE . $entity . " as e";
        $relations = [];
        $result = [];
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

            $command .= " LEFT JOIN " . PRE . $info['system'] . " as system_" . $info['system'] . " ON system_" . $info['system'] . ".id = e.system_id";
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

            $command .= " LEFT JOIN " . PRE . "usuarios as autor_user ON autor_user.id = e." . ($info['autor'] == 1 ? "autorpub" : "ownerpub");
        }

        /**
         * Include the data from each relation
         */
        if (!empty($info['relation'])) {
            foreach ($info['relation'] as $relationItem) {
                $relationEntity = $dicionario[$relationItem]['relation'];
                $relations[$relationEntity] = $dicionario[$relationItem]['column'];

                if (!isset($dicionarios[$relationEntity]))
                    $dicionarios[$relationEntity] = Metadados::getDicionario($relationEntity);

                if (!isset($infos[$relationEntity]))
                    $infos[$relationEntity] = Metadados::getInfo($relationEntity);

                if (!empty($infos[$relationEntity]['columns_readable'])) {
                    foreach ($infos[$relationEntity]['columns_readable'] as $column)
                        $selects .= ", data_" . $dicionario[$relationItem]['column'] . ".{$column} as {$dicionario[$relationItem]['relation']}___{$column}";
                }

                $command .= " LEFT JOIN " . PRE . $dicionario[$relationItem]['relation'] . " as data_" . $dicionario[$relationItem]['column'] . " ON data_" . $dicionario[$relationItem]['column'] . ".id = e." . $dicionario[$relationItem]['column'];
            }
        }

        /**
         * Set the limit result to return to front
         */
        $command = "SELECT " . $selects . " {$command}" . (is_numeric($id) && $id > 0 ? " WHERE e.id = {$id}" : "") . " ORDER BY id DESC";

        /**
         * Execute the read command
         */
        $sql = new SqlCommand();
        $sql->exeCommand($command);

        /**
         * Save total register if not have ID
         */
        if(!$id || !is_numeric($id) || $id < 1) {
            \Helpers\Helper::createFolderIfNoExist(PATH_HOME . "_cdn/userTotalRegisterDB");
            \Helpers\Helper::createFolderIfNoExist(PATH_HOME . "_cdn/userTotalRegisterDB/{$_SESSION['userlogin']['id']}");
            $f = fopen(PATH_HOME . "_cdn/userTotalRegisterDB/" . $_SESSION['userlogin']['id'] . "/{$entity}.json", "w");
            fwrite($f, $sql->getRowCount());
            fclose($f);
        }

        /**
         * Convert join values into a array of relation data
         * Convert json values into array
         */
        if (empty($sql->getErro()) && !empty($sql->getResult())) {
            foreach ($sql->getResult() as $i => $register) {
                if ($i >= LIMITOFFLINE)
                    break;

                /**
                 * Work on a variable with the data of relationData
                 */
                $relationData = [];

                /**
                 * convert data from default format
                 */
                foreach ($dicionario as $meta) {
                    $m = new \Entity\Meta($meta);
                    $m->setValue($register[$meta['column']]);
                    $register[$meta['column']] = $m->getValue();
                }

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
                        foreach ($relations as $relation => $RelationColumn) {
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
                            $m = new \Entity\Meta($meta);
                            $m->setValue($relationData["system_id"][$meta['column']]);
                            $relationData["system_id"][$meta['column']] = $m->getValue();
                        }
                    }
                }

                if (!empty($info['autor'])) {
                    /**
                     * Check if the struct of relation data received have a ID
                     * if not, so delete
                     */
                    if (empty($relationData["usuarios"]['id'])) {
                        unset($relationData["usuarios"]);

                    } else {

                        /**
                         * Decode all json on base relation register
                         */

                        foreach ($dicionarios["usuarios"] as $meta) {
                            $m = new \Entity\Meta($meta);
                            $m->setValue($relationData["usuarios"][$meta['column']]);
                            $relationData["usuarios"][$meta['column']] = $m->getValue();
                        }

                        $relationData[$info['autor'] == 1 ? "autorpub" : "ownerpub"] = $relationData["usuarios"];
                        unset($relationData["usuarios"]);
                    }
                }

                /**
                 * After separate the base data from the relation data
                 * check if the relation data have a ID an decode json
                 */
                if (!empty($relations)) {
                    foreach ($relations as $relation => $RelationColumn) {

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
                                $m = new \Entity\Meta($meta);
                                $m->setValue($relationData[$RelationColumn][$meta['column']]);
                                $relationData[$RelationColumn][$meta['column']] = $m->getValue();
                            }

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
                $read = new \Conn\Read();
                foreach ($result as $i => $item) {
                    $entitySetor = ($entity === "usuarios" ? $item['setor'] : ($info['autor'] == 1 ? $item['relationData']["autorpub"]['setor'] : $item['relationData']["ownerpub"]['setor']));
                    if (!empty($entitySetor)) {

                        if (!isset($infos[$entitySetor]))
                            $infos[$entitySetor] = Metadados::getInfo($entitySetor);

                        if (!isset($dicionarios[$entitySetor]))
                            $dicionarios[$entitySetor] = Metadados::getDicionario($entitySetor);

                        if (!empty($infos[$entitySetor]['columns_readable']))
                            $read->setSelect($infos[$entitySetor]['columns_readable']);

                        $idUsuario = ($entity === "usuarios" ? $item['id'] : ($info['autor'] == 1 ? $item['relationData']["autorpub"]['id'] : $item['relationData']["ownerpub"]['id']));
                        $read->exeRead($entitySetor, "WHERE usuarios_id = :id", "id={$idUsuario}", !0);
                        if ($read->getResult()) {
                            if($entity === "usuarios")
                                $result[$i]['relationData'][$entitySetor] = [];
                            else
                                $result[$i]['relationData'][($info['autor'] == 1 ? "autorpub" : "ownerpub")]["relationData"][$entitySetor] = [];

                            /**
                             * Decode all json on base relation register
                             */
                            foreach ($dicionarios[$entitySetor] as $meta) {
                                $m = new \Entity\Meta($meta);
                                $m->setValue($read->getResult()[0][$meta['column']]);
                                if($entity === "usuarios")
                                    $result[$i]['relationData'][$entitySetor][$meta['column']] = $m->getValue();
                                else
                                    $result[$i]['relationData'][($info['autor'] == 1 ? "autorpub" : "ownerpub")]["relationData"][$entitySetor][$meta['column']] = $m->getValue();
                            }
                        }
                    }
                }
            }
        }

        return $result;
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

        $read->exeRead($entity, "WHERE usuarios_id = :id", "id={$id}", !0);
        if ($read->getResult()) {
            /**
             * Decode all json on base relation register
             */
            foreach ($dicionario as $meta) {
                $m = new \Entity\Meta($meta);
                $m->setValue($read->getResult()[0][$meta['column']]);
                $result[$meta['column']] = $m->getValue();
            }
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

                    if ($entidade === $_SESSION['userlogin']['setor'] || Config::haveEntityPermission($entidade)) {
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
}