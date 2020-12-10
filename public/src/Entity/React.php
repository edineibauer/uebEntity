<?php

namespace Entity;

use Config\Config;
use Conn\Create;
use Conn\Delete;
use Conn\Read;
use Conn\SqlCommand;
use Conn\Update;
use Helpers\Helper;

class React
{
    private $response;

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * React constructor.
     * @param string $action
     * @param string $entity
     * @param array $dados
     * @param array $dadosOld
     */
    public function __construct(string $action, string $entity, array $dados, array $dadosOld = [])
    {
        $data = ["data" => "", "response" => 1, "error" => ""];
        if (!preg_match("/^wcache_/i", $entity)) {
            $setor = Config::getSetor();

            /**
             * Create log with the transition to general purpose
             */
            $this->log($action, $entity, $dados, $dadosOld);

            /**
             *  generate/update cached database with this entity information
             *  this is for keep the cached database up to date
             *  the cached database have all relation and formatted data for general fast consult purpose
             */
            if ($action === "delete") {
                $del = new Delete();
                $del->exeDelete("wcache_" . $entity, "WHERE id = {$dados['id']}");
            } else {
                $this->updateCachedDatabase($entity, $dados, $action);
            }

            if(!empty($_SESSION['userlogin']) && $_SESSION['userlogin']['id'] > 0) {
                $this->checkSseUpdate($action, $entity, $dados);
                $this->checkGetUpdate($action, $entity, $dados);
            }

            $this->createUpdateSyncIndexedDb($action, $entity, $dados['id']);

            /**
             * Include react for this operation if have
             */
            if (file_exists(PATH_HOME . "public/react/{$setor}/{$entity}/{$action}.php"))
                include PATH_HOME . "public/react/{$setor}/{$entity}/{$action}.php";
            elseif (file_exists(PATH_HOME . "public/react/{$entity}/{$action}.php"))
                include PATH_HOME . "public/react/{$entity}/{$action}.php";

            foreach (Helper::listFolder(PATH_HOME . VENDOR) as $lib) {
                if (file_exists(PATH_HOME . VENDOR . "{$lib}/public/react/{$setor}/{$entity}/{$action}.php"))
                    include PATH_HOME . VENDOR . "{$lib}/public/react/{$setor}/{$entity}/{$action}.php";
                elseif (file_exists(PATH_HOME . VENDOR . "{$lib}/public/react/{$entity}/{$action}.php"))
                    include PATH_HOME . VENDOR . "{$lib}/public/react/{$entity}/{$action}.php";
            }
        }

        $this->response = $data;
    }

    /**
     * @param string $action
     * @param string $entity
     * @param array $dados
     */
    private function checkSseUpdate(string $action, string $entity, array $dados)
    {
        if(file_exists(PATH_HOME . "_config/sse.json")) {
            $sses = json_decode(file_get_contents(PATH_HOME . "_config/sse.json"), !0);
            foreach ($sses as $sse => $contentSse) {
                foreach ($contentSse['db'] as $db => $rules) {

                    /**
                     * Entity is the same and action is listening
                     */
                    if($db === $entity && in_array($action, $rules)) {

                        /**
                         * If the system is empty or same
                         */
                        if(empty($_SESSION['userlogin']['system_id']) || empty($dados['system_id']) || $dados['system_id'] === $_SESSION['userlogin']['system_id']) {

                            /**
                             * If the owner is empty or same
                             */
                            if(!isset($dados['ownerpub']) || $dados['ownerpub'] === $_SESSION['userlogin']['id']) {

                                /**
                                 * If the file sse exist, so remove it to update
                                 */
                                if(file_exists(PATH_HOME . "_cdn/userSSE/" . $_SESSION['userlogin']['id'] . "/sse/{$sse}.json"))
                                    unlink(PATH_HOME . "_cdn/userSSE/" . $_SESSION['userlogin']['id'] . "/sse/{$sse}.json");
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param string $action
     * @param string $entity
     * @param array $dados
     */
    private function checkGetUpdate(string $action, string $entity, array $dados)
    {
        if(file_exists(PATH_HOME . "_cdn/userSSE/" . $_SESSION['userlogin']['id'] . "/get")) {
            foreach (Helper::listFolder(PATH_HOME . "_cdn/userSSE/" . $_SESSION['userlogin']['id'] . "/get") as $item) {
                $c = json_decode(file_get_contents(PATH_HOME . "_cdn/userSSE/" . $_SESSION['userlogin']['id'] . "/get/{$item}"), !0);
                if($c['haveUpdate'] === "0" && !empty($c['db'])) {
                    foreach ($c['db'] as $entityDb => $rules) {
                        if($entityDb === "usuarios" && $entity === $_SESSION['userlogin']['setor'] && $action === "update" && $dados['id'] == $_SESSION['userlogin']['setorData']['id']) {
                            /**
                             * My Perfil
                             */
                            $c['haveUpdate'] = 1;
                            Config::createFile(PATH_HOME . "_cdn/userSSE/" . $_SESSION['userlogin']['id'] . "/get/{$item}", json_encode($c));
                            break;

                        } elseif($entity === $entityDb && (empty($rules) || in_array($action, $rules))) {
                            /**
                             * If the system is empty or same
                             */
                            if(empty($_SESSION['userlogin']['system_id']) || empty($dados['system_id']) || $dados['system_id'] === $_SESSION['userlogin']['system_id']) {

                                /**
                                 * If the owner is empty or same
                                 */
                                if(!isset($dados['ownerpub']) || $dados['ownerpub'] === $_SESSION['userlogin']['id']) {
                                    $c['haveUpdate'] = "1";
                                    Config::createFile(PATH_HOME . "_cdn/userSSE/" . $_SESSION['userlogin']['id'] . "/get/{$item}", json_encode($c));
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Cria atualizações que dizem para o front o que ele deve receber de alteração
     *
     * @param string $action
     * @param string $entity
     * @param int $id
     */
    private function createUpdateSyncIndexedDb(string $action, string $entity, int $id)
    {
        $list = Helper::listFolder(PATH_HOME . "_cdn/update/{$entity}");
        $idHistoric = (!empty($list) ? (((int)str_replace(".json", "", explode("-", $list[count($list) - 1])[1])) + 1) : 1);

        $json = new Json();
        $hist = $json->get("historic");
        $hist[$entity] = (string)strtotime('now') . "-" . $idHistoric;
        $json->save("historic", $hist);

        $store = new Json("update/{$entity}");
        $store->setVersionamento(!1);
        $this->limitaAtualizaçõesArmazenadas($action, $entity, $id);
        $store->save($hist[$entity], ['db_action' => $action, "id" => $id]);
    }

    /**
     * @param string $action
     * @param string $entity
     * @param int $id
     */
    private function limitaAtualizaçõesArmazenadas(string $action, string $entity, int $id)
    {
        //remove updates anteriores de registros que serão excluídos
        if ($action === "delete") {
            foreach (\Helpers\Helper::listFolder(PATH_HOME . "_cdn/update/{$entity}") as $historie) {
                $dadosE = json_decode(file_get_contents(PATH_HOME . "_cdn/update/{$entity}/{$historie}"), !0);
                if ($dadosE['db_action'] !== "delete" && !empty($dadosE['id']) && !empty($id) && $dadosE['id'] == $id)
                    unlink(PATH_HOME . "_cdn/update/{$entity}/{$historie}");
            }
        }

        //se tiver mais que 100 resultados, deleta os acima de 100
        $lista = \Helpers\Helper::listFolder(PATH_HOME . "_cdn/update/{$entity}");
        $limite = 200;
        if ((count($lista) + 1) > $limite) {
            $totalExcluir = count($lista) - 100;
            for ($i = 0; $i < $totalExcluir; $i++) {
                if (isset($lista[$i]))
                    unlink(PATH_HOME . "_cdn/update/{$entity}/{$lista[$i]}");
            }
        }
    }

    /**
     * Cria log da atividade executada.
     * @param string $action
     * @param string $entity
     * @param array $dados
     * @param array $old
     */
    public function log(string $action, string $entity, array $dados, array $old)
    {
        $store = new Json("store/{$entity}", 20);
        $dados['userlogin'] = (!empty($_SESSION['userlogin']) ? $_SESSION['userlogin']['id'] : 0);

        if ($action === "create") {
            $store->save($dados['id'], $dados);
        } else {
            if ($action === "delete") {
                if (!empty($old['id']))
                    $store->delete($old['id']);
            } else {
                $store->save($old['id'], array_merge($old, $dados));
            }
        }
    }

    /**
     * the cached database have all relation and formatted data for general purpose
     * fast consult on sseEngineDB when read the entity information
     *
     * @param string $entity
     * @param array $dados
     * @param string $action
     */
    private function updateCachedDatabase(string $entity, array $dados, string $action)
    {
        $register = Entity::exeReadWithoutCache($entity, $dados['id']);

        if (!empty($register)) {
            $register = $register[0];
            $dataCache = ["id" => $dados['id'], "system_id" => null, "data" => json_encode($register)];

            if ($action === "create") {
                $create = new Create();
                $create->exeCreate("wcache_{$entity}", $dataCache);

            } else {

                $read = new Read();
                $read->exeRead("wcache_{$entity}", "WHERE id = :id", "id={$dados['id']}");
                if ($read->getResult()) {
                    $up = new Update();
                    $up->exeUpdate("wcache_{$entity}", $dataCache, "WHERE id = :id", "id={$dados['id']}");
                } else {
                    $create = new Create();
                    $create->exeCreate("wcache_{$entity}", $dataCache);
                }

                /**
                 * Check other databases with relation to this to
                 * delete her caches outdated
                 */
                if (file_exists(PATH_HOME . "entity/general/general_info.json")) {
                    $general = json_decode(file_get_contents(PATH_HOME . "entity/general/general_info.json"), !0)[$entity]['belongsTo'];
                    if (!empty($general)) {
                        $sql = new SqlCommand();
                        foreach ($general as $itemEntity => $item) {
                            if (!empty($itemEntity) && !empty($item['column']) && file_exists(PATH_HOME . "entity/cache/{$itemEntity}.json"))
                                $sql->exeCommand("DELETE c FROM " . PRE . "wcache_" . $itemEntity . " as c JOIN " . PRE . $itemEntity . " as e ON e.id = c.id WHERE e." . $item['column'] . " = {$dados['id']}");
                        }
                    }
                }
            }
        }
    }
}