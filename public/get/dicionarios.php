<?php
$dic = Entity\Entity::dicionario();

//convert dicionário para referenciar colunas e não ids
$dicionario = [];
$dicionarioOrdenado = [];
foreach ($dic as $entity => $metas) {
    $indice = 99999;
    foreach ($metas as $i => $meta) {
        if($meta['key'] !== "identifier") {
            $meta['id'] = $i;
            $dicionario[$entity][$meta['indice'] ?? $indice++] = $meta;
        }
    }
    ksort($dicionario[$entity]);
    foreach ($dicionario[$entity] as $i => $meta)
        $dicionarioOrdenado[$entity][$meta['column']] = $meta;

    $info = \Entity\Metadados::getInfo($entity);
    if($info['autor'] === 1) {
        $inputType = json_decode(file_get_contents(PATH_HOME . VENDOR . "entity-ui/public/entity/input_type.json"), true);
        $dicionarioOrdenado[$entity]['autorpub'] = array_replace_recursive($inputType['default'], $inputType['publisher'], ["indice" => 999999, "default" => $_SESSION['userlogin']['id']]);
    }

}

$data['data'] = $dicionarioOrdenado;