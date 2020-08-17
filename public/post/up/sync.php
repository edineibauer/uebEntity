<?php

if(!empty($_SESSION['userlogin'])) {
    $entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
    $dados = filter_input(INPUT_POST, 'dados', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

    \Helpers\Helper::createFolderIfNoExist(PATH_HOME . '_cdn/sync');

    $dir = PATH_HOME . "_cdn/sync/" . $_SESSION['userlogin']['id'] . ".json";
    if (!file_exists($dir)) {
        $f = fopen($dir, "w+");
        fwrite($f, json_encode($dados));
        fclose($f);
    }
}