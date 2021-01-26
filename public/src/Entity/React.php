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
        $setor = Config::getSetor();

        if ($action === "update") {
            $isDiff = false;
            foreach ($dados as $c => $v) {
                if (isset($dadosOld[$c]) && $v != $dadosOld[$c]) {
                    $isDiff = true;
                    break;
                }
            }

            if (!$isDiff)
                return;
        }

        /**
         * Create log with the transition to general purpose
         */
        $this->log($action, $entity, $dados, $dadosOld);

        /**
         * Check if need to update some sse or get
         */
        $this->checkSseUpdates("sse", $action, $entity, $dados);
        $this->checkSseUpdates("get", $action, $entity, $dados);

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

        $this->response = $data;
    }

    /**
     * @param string $dir
     * @param string $action
     * @param string $entity
     * @param array $dados
     */
    private function checkSseUpdates(string $dir, string $action, string $entity, array $dados)
    {
        if (file_exists(PATH_HOME . "_cdn/userSSE")) {
            foreach (Helper::listFolder(PATH_HOME . "_cdn/userSSE") as $user) {
                $path = PATH_HOME . "_cdn/userSSE/{$user}/{$dir}";
                if (file_exists($path) && file_exists(PATH_HOME . "_cdn/userSSE/{$user}/my_data.json")) {
                    $userData = json_decode(file_get_contents(PATH_HOME . "_cdn/userSSE/{$user}/my_data.json"), !0);

                    if(!empty($userData) && is_array($userData))
                        $this->_checkSseUpdatesWithUser($path, $action, $entity, $dados, $userData);
                }
            }
        }
    }

    /**
     * @param string $path
     * @param string $action
     * @param string $entity
     * @param array $dados
     * @param array $userData
     */
    private function _checkSseUpdatesWithUser(string $path, string $action, string $entity, array $dados, array $userData)
    {
        foreach (Helper::listFolder($path) as $item) {
            $c = json_decode(file_get_contents($path . "/{$item}"), !0);

            if((isset($c['rule']) && $c['rule'] !== "db") || (isset($c['haveUpdate']) && $c['haveUpdate'] == "1") || empty($c['db']) || empty($c['action']))
                continue;

            /**
             * If is the user entity to update (perfil), so update all SSE
             */
            if ($action === "update" && (($entity === "usuarios" && $userData['id'] == $dados['id']) || ($entity === $userData['setor'] && $userData['setorData']['id'] == $dados['id']) || ($entity !== $userData['setor'] && $entity !== "usuarios"))) {
                $c['haveUpdate'] = "1";
                Config::createFile($path . "/{$item}", json_encode($c));

                continue;
            }

            /**
             * Check action
             * (IFs in cascate to better human understand)
             */
            if((is_string($c['action']) && $c['action'] === $action) || (is_array($c['action']) && in_array($action, $c['action']))) {

                /**
                 * Check DB
                 */
                if ((is_string($c['db']) && $c['db'] === $entity) || (is_array($c['db']) && in_array($entity, $c['db']))) {

                    /**
                     * If the system is empty or same
                     */
                    if (empty($userData['system_id']) || empty($dados['system_id']) || $dados['system_id'] == $userData['system_id']) {

                        /**
                         * If the owner is empty or same
                         */
                        if (empty($dados['ownerpub']) || $dados['ownerpub'] == $userData['id']) {
                            $c['haveUpdate'] = "1";
                            Config::createFile($path . "/{$item}", json_encode($c));
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
}