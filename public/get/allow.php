<?php

$data['data'] = [];
if(file_exists(PATH_HOME . "_config/permissoes.json"))
    $data['data'] = json_decode(file_get_contents(PATH_HOME . "_config/permissoes.json"), true);