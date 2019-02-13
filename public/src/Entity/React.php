<?php

namespace Entity;

use Helpers\Helper;

class React
{
    /**
     * React constructor.
     * @param string $action
     * @param string $entity
     * @param array $dados
     * @param array $dadosOld
     */
    public function __construct(string $action, string $entity, array $dados, array $dadosOld = [])
    {
        $this->log($action, $entity, $dados);

        if (!empty($_SESSION['userlogin']['setor']) && file_exists(PATH_HOME . "public/react/{$_SESSION['userlogin']['setor']}/{$entity}/{$action}.php"))
            include PATH_HOME . "public/react/{$_SESSION['userlogin']['setor']}/{$entity}/{$action}.php";
        elseif (file_exists(PATH_HOME . "public/react/{$entity}/{$action}.php"))
            include PATH_HOME . "public/react/{$entity}/{$action}.php";

        foreach (Helper::listFolder(PATH_HOME . VENDOR) as $lib) {
            if (!empty($_SESSION['userlogin']['setor']) && file_exists(PATH_HOME . VENDOR . "{$lib}/public/react/{$_SESSION['userlogin']['setor']}/{$entity}/{$action}.php"))
                include PATH_HOME . VENDOR . "{$lib}/public/react/{$_SESSION['userlogin']['setor']}/{$entity}/{$action}.php";
            elseif (file_exists(PATH_HOME . VENDOR . "{$lib}/public/react/{$entity}/{$action}.php"))
                include PATH_HOME . VENDOR . "{$lib}/public/react/{$entity}/{$action}.php";
        }
    }

    /**
     * Cria log da atividade executada.
     * @param string $action
     * @param string $entity
     * @param array $dados
     * @param array $dadosOld
     */
    public function log(string $action, string $entity, array $dados)
    {
        $store = new Json("store/{$entity}");

        if(!empty($_SESSION['userlogin']))
            $dados['userlogin'] = $_SESSION['userlogin']['id'];

        if($action === "delete")
            $store->delete($dados['id']);
        else
            $store->save($dados['id'], $dados);
    }
}