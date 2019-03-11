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

        $this->log($action, $entity, $dados);
        $this->createUpdateSyncIndexedDb($action, $entity, $dados);

        $this->response = $data;
    }

    /**
     * @param string $action
     * @param string $entity
     * @param array $dados
     */
    private function createUpdateSyncIndexedDb(string $action, string $entity, array $dados)
    {
        //salva historico de alterações
        $json = new Json();
        $hist = $json->get("historic");
        $hist[$entity] = strtotime('now');
        $json->save("historic", $hist);

        $d = new Dicionario($entity);
        foreach ($d->getDicionario() as $meta) {
            if($meta->getFormat() === "password")
                $dados[$meta->getColumn()] = null;
        }

        $this->limitaAtualizaçõesArmazenadas($action, $entity, $dados);
        $dados['db_action'] = $action;

        $store = new Json("update/{$entity}");
        $store->setVersionamento(false);
        $store->save($hist[$entity], $dados);
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
                $dadosE = json_decode(file_get_contents(PATH_HOME . "_cdn/update/{$entity}/{$historie}"), true);
                if (is_array($dadosE)) {
                    foreach ($dadosE as $dado) {
                        if (!empty($dado['id']) && !empty($dados['id']) && $dado['id'] == $dados['id'])
                            unlink(PATH_HOME . "_cdn/update/{$entity}/{$historie}");
                    }
                } elseif (!empty($dadosE['id']) && $dadosE['id'] == $dados['id']) {
                    unlink(PATH_HOME . "_cdn/update/{$entity}/{$historie}");
                }
            }
        }

        //se tiver mais que 100 resultados, deleta os acima de 100
        if (count($total = Helper::listFolder(PATH_HOME . "_cdn/update/{$entity}")) > 99) {
            $excluir = 101 - count($total);
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
     */
    public function log(string $action, string $entity, array $dados)
    {
        $store = new Json("store/{$entity}");

        if (!empty($_SESSION['userlogin']))
            $dados['userlogin'] = $_SESSION['userlogin']['id'];

        if ($action === "delete")
            $store->delete($dados['id']);
        else
            $store->save($dados['id'], $dados);
    }
}