<?php

if(!empty($_SESSION['userlogin'])) {
    $entity = filter_input(INPUT_POST, 'entity', FILTER_DEFAULT);
    $dados = filter_input(INPUT_POST, 'dados', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

    \Helpers\Helper::createFolderIfNoExist(PATH_HOME . '_cdn/sync');
    \Helpers\Helper::createFolderIfNoExist(PATH_HOME . "_cdn/sync/" . $_SESSION['userlogin']['id']);

    $dir = PATH_HOME . "_cdn/sync/" . $_SESSION['userlogin']['id'] . "/" . $entity . ".json";
    if (!file_exists($dir)) {
        function recursiveJson($dados)
        {
            foreach ($dados as $key => $dado) {
                if (!empty($dado) && is_string($dado) && \Helpers\Check::isJson($dado)) {
                    $dados[$key] = str_replace('"', '\"', $dado);
                } elseif (is_array($dado)) {
                    $dados[$key] = recursiveJson($dado);
                }
            }

            return $dados;
        }

        $f = fopen($dir, "w+");
        fwrite($f, json_encode(recursiveJson($dados)));
        fclose($f);
    }
}