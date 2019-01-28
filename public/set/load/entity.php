<?php

use \Helpers\Helper;

$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
$historicFront = filter_input(INPUT_POST, 'historic', FILTER_VALIDATE_INT);
$setor = empty($_SESSION['userlogin']['setor']) ? 0 : (int)$_SESSION['userlogin']['setor'];
$permissoes = \Config\Config::getPermission();
$json = new \Entity\Json();
$hist = $json->get("historic");
$data['data'] = ['historic' => 0];

if ($setor === 1 || (isset($permissoes[$setor][$entity]['read']) || $permissoes[$setor][$entity]['read'])) {

    //preenche caso não tenha nada de informação
    if (empty($hist[$entity])) {
        $hist[$entity] = strtotime('now');
        $json->save("historic", $hist);
    }

    //verifica se há alterações nessa entidade que não forão recebidas pelo app, caso tenha, atualiza os dados
    if (empty($historicFront) || !file_exists(PATH_HOME . "_cdn/update/{$entity}/{$historicFront}.json")) {
        //download all data

        //Verifica se é multitenancy, se for, adiciona cláusula para buscar somente os dados referentes ao usuário
        $info = \Entity\Metadados::getInfo($entity);
        $where = null;
        if ($setor !== 1 && !empty($info['autor']) && $info['autor'] === 2)
            $where = "WHERE ownerpub = " . $_SESSION['userlogin']['id'];

        $read = new \Conn\Read();
        $read->exeRead($entity, $where);
        $data['data']['data'] = $read->getResult() ?? [];
        $data['data']['tipo'] = 1;
        $data['data']['historic'] = $hist[$entity];

    } elseif ($historicFront < $hist[$entity]) {
        //download updates
        $data['data']['data'] = [];
        foreach (Helper::listFolder(PATH_HOME . "_cdn/update/{$entity}") as $update) {
            $historicUpdate = (int)str_replace('.json', '', $update);
            if ($historicFront < $historicUpdate) {
                $dadosUp = json_decode(file_get_contents(PATH_HOME . "_cdn/update/{$entity}/{$update}"), true);
                if (!empty($dadosUp))
                    $data['data']['data'] = array_merge($data['data']['data'], $dadosUp);
            }
        }

        $data['data']['tipo'] = 2;
        $data['data']['historic'] = $hist[$entity];
    }
}