<?php
$entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
$time = filter_input(INPUT_POST, 'historic', FILTER_VALIDATE_INT);

$setor = empty($_SESSION['userlogin']['setor']) ? 0 : $_SESSION['userlogin']['setor'];
$entidades = \Config\Config::getEntityNotAllow();

$json = new \Entity\Json();
$hist = $json->get("historic");
$data['data'] = [];

if (\Entity\Entity::checkPermission($entity) && (empty($entidades[$setor]) || !in_array($entity, $entidades[$setor]))) {
    //preenche caso não tenha nada de informação
    if (empty($hist[$entity])) {
        $hist[$entity] = strtotime('now');
        $json->save("historic", $hist);
    }

    //verifica se há alterações nessa entidade que não forão recebidas pelo app
    if (empty($time) || $time < $hist[$entity]) {
        $read = new \Conn\Read();
        $read->exeRead($entity);
        $data['data']['data'] = $read->getResult() ?? [];

        //atualiza histórico local
        $data['data']['historic'] = $hist[$entity];
    } else {
        $data['data']['historic'] = 0;
    }
}