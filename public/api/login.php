<?php

use Login\Login;

$dados = $_POST;
$dados['user'] = strip_tags(trim($dados['user']));
$dados['password'] = trim($dados["password"]);

if(!empty($dados['user']) && !empty($dados['password'])) {
    $login = new Login($dados);
    $data['data'] = $login->getResult();
} else {
    if(!empty($dados['user']))
        $data['data'] = "Informe o Usuário";
    else
        $data['data'] = "Informe a Senha";
}