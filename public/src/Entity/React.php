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

        $this->log($action, $entity, $dados, $dadosOld);
        $this->createUpdateSyncIndexedDb($action, $entity, $dados, $dadosOld);

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
     * @param string $action
     * @param string $entity
     * @param array $dados
     * @param array $old
     */
    private function createUpdateSyncIndexedDb(string $action, string $entity, array $dados, array $old)
    {
        //salva historico de alterações

        $list = Helper::listFolder(PATH_HOME . "_cdn/update/{$entity}");
        $id = (!empty($list) ? (((int) str_replace(".json", "", explode("-", $list[count($list) -1])[1])) + 1) : 1);

        $json = new Json();
        $hist = $json->get("historic");
        $hist[$entity] = strtotime('now') . "-" . $id;
        $json->save("historic", $hist);

        $store = new Json("update/{$entity}");
        $store->setVersionamento(!1);

        $d = new Dicionario($entity);
        foreach ($d->getDicionario() as $meta) {
            if ($meta->getFormat() === "password")
                $dados[$meta->getColumn()] = null;
        }

        $this->limitaAtualizaçõesArmazenadas($action, $entity, $dados);

        if ($action === "delete") {
            $store->save($hist[$entity], array_merge(['db_action' => "delete"], $dados));

        } elseif ($action === "update") {
            $store->save($hist[$entity], array_merge($old, ['db_action' => "update"], $dados));

        } else {
            $store->save($hist[$entity], array_merge(['db_action' => "create"], $dados));
        }
    }

    /**
     * @param string $action
     * @param string $entity
     * @param array $dados
     */
    private function limitaAtualizaçõesArmazenadas(string $action, string $entity, array $dados)
    {
        //remove updates anteriores de registros que serão excluídos
        if ($action === "delete") {
            foreach (\Helpers\Helper::listFolder(PATH_HOME . "_cdn/update/{$entity}") as $historie) {
                $dadosE = json_decode(file_get_contents(PATH_HOME . "_cdn/update/{$entity}/{$historie}"), !0);
                if ($dadosE['db_action'] !== "delete" && !empty($dadosE['id']) && !empty($dados['id']) && $dadosE['id'] == $dados['id'])
                    unlink(PATH_HOME . "_cdn/update/{$entity}/{$historie}");
            }
        }

        //se tiver mais que 100 resultados, deleta os acima de 100
        $lista = \Helpers\Helper::listFolder(PATH_HOME . "_cdn/update/{$entity}");
        $limite = 100;
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
        $store = new Json("store/{$entity}");
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