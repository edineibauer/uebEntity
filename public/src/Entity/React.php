<?php

namespace Entity;

use Conn\Create;
use Conn\Read;
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
        $setor = !empty($_SESSION['userlogin']) ? $_SESSION['userlogin']['setor'] : "0";

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

        $this->log($action, $entity, $dados, $dadosOld);
        $this->createUpdateSyncIndexedDb($action, $entity, $dados, $dadosOld);

        $this->response = $data;
    }

    /**
     * @param string $action
     * @param string $entity
     * @param array $dados
     * @param array $old
     */
    private function createUpdateSyncIndexedDb(string $action, string $entity, array $dados, array $old)
    {
        //salva historico de alterações
        $json = new Json();
        $hist = $json->get("historic");
        $hist[$entity] = strtotime('now') . "-" . rand(1000000, 9999999);
        $json->save("historic", $hist);

        $store = new Json("update/{$entity}");
        $store->setVersionamento(!1);

        $d = new Dicionario($entity);
        foreach ($d->getDicionario() as $meta) {
            if ($meta->getFormat() === "password")
                $dados[$meta->getColumn()] = null;
        }

        $this->limitaAtualizaçõesArmazenadas($action, $entity, $dados, $old);

        if ($action === "delete") {
            $store->save($hist[$entity], array_merge(['db_action' => "delete"], $dados));

        } elseif ($action === "update") {
            foreach ($old as $item)
                $store->save($hist[$entity], array_merge($item, ['db_action' => "update"], $dados));

        } else {
            $store->save($hist[$entity], array_merge(['db_action' => "create"], $dados));
        }
    }

    /**
     * @param string $action
     * @param string $entity
     * @param array $dados
     * @param array $old
     */
    private function limitaAtualizaçõesArmazenadas(string $action, string $entity, array $dados, array $old)
    {
        //remove updates anteriores de registros que serão excluídos
        if ($action === "delete") {
            foreach (\Helpers\Helper::listFolder(PATH_HOME . "_cdn/update/{$entity}") as $historie) {
                $dadosE = json_decode(file_get_contents(PATH_HOME . "_cdn/update/{$entity}/{$historie}"), !0);
                if ($dadosE['db_action'] !== "delete" && $dadosE['id'] == $dados['id'])
                    unlink(PATH_HOME . "_cdn/update/{$entity}/{$historie}");
            }
        }

        //se tiver mais que 100 resultados, deleta os acima de 100
        $total = count(Helper::listFolder(PATH_HOME . "_cdn/update/{$entity}")) + ($action !== "create" ? count($old) : 1);
        if ($total > 99) {
            $excluir = 101 - $total;
            for ($i = 0; $i < $excluir; $i++) {
                if (isset($total[$i])) {
                    unlink(PATH_HOME . "_cdn/update/{$entity}/{$total[$i]}");
                } else {
                    break;
                }
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
        $store = new Json("store/{$entity}");
        $dados['userlogin'] = (!empty($_SESSION['userlogin']) ? $_SESSION['userlogin']['id'] : 0);

        if ($action === "create") {
            $store->save($dados['id'], $dados);
        } else {
            foreach ($old as $item) {
                if ($action === "delete") {
                    if (!empty($item['id']))
                        $store->delete($item['id']);
                } else {
                    $store->save($item['id'], array_merge($item, $dados));
                }
            }
        }
    }
}