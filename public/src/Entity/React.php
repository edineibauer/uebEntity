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
                if (!isset($dadosOld[$c]) || $v != $dadosOld[$c]) {
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
//        $this->log($action, $entity, $dados, $dadosOld);

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
     * Cria log da atividade executada.
     * @param string $action
     * @param string $entity
     * @param array $dados
     * @param array $old
     */
    public function log(string $action, string $entity, array $dados, array $old)
    {
        $store = new Json("log/{$entity}", 50);
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