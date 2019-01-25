<?php
$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
$time = filter_input(INPUT_POST, 'historic', FILTER_VALIDATE_INT);

$setor = empty($_SESSION['userlogin']['setor']) ? 0 : (int) $_SESSION['userlogin']['setor'];
$permissoes = \Config\Config::getPermission();

$json = new \Entity\Json();
$hist = $json->get("historic");
$data['data'] = [];

if ($setor === 1 || (isset($permissoes[$setor][$entity]['read']) || $permissoes[$setor][$entity]['read'])) {
    //Verifica se é multitenancy, se for, adiciona cláusula para buscar somente os dados referentes ao usuário
    $info = \Entity\Metadados::getInfo($entity);
    $where = null;
    if($setor !== 1 && !empty($info['autor']) && $info['autor'] === 2)
        $where = "WHERE ownerpub = " . $_SESSION['userlogin']['id'];

    //preenche caso não tenha nada de informação
    if (empty($hist[$entity])) {
        $hist[$entity] = strtotime('now');
        $json->save("historic", $hist);
    }

    //verifica se há alterações nessa entidade que não forão recebidas pelo app, caso tenha, atualiza os dados
    if (empty($time) || $time < $hist[$entity]) {
        $read = new \Conn\Read();
        $read->exeRead($entity, $where);
        $data['data']['data'] = $read->getResult() ?? [];

        //atualiza histórico local
        $data['data']['historic'] = $hist[$entity];
    } else {
        $data['data']['historic'] = 0;
    }
}