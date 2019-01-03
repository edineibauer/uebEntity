<?php
$dic = Entity\Entity::dicionario();

//convert dicionário para referenciar colunas e não ids
$dicionario = [];
foreach ($dic as $entity => $metas) {
    foreach ($metas as $i => $meta) {
        $meta['id'] = $i;
        $dicionario[$entity][$meta['column']] = $meta;
    }
}

$data['data'] = $dicionario;