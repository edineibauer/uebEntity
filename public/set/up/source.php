<?php

$data = ['error' => 0, 'data' => [], 'response' => 1];

$name = strip_tags(trim(filter_input(INPUT_POST, 'name', FILTER_DEFAULT)));
$fileType = strip_tags(trim(filter_input(INPUT_POST, 'fileType', FILTER_DEFAULT)));
$extensao = strip_tags(trim(filter_input(INPUT_POST, 'type', FILTER_DEFAULT)));
$source = strip_tags(trim(filter_input(INPUT_POST, 'url', FILTER_DEFAULT)));

if (!empty($source) && is_string($source) && preg_match('/;/i', $source)) {
    list($type, $dd) = explode(';', $source);
    list(, $dd) = explode(',', $dd);
    $file_data = base64_decode(str_replace(' ', "+", $dd));

    \Helpers\Helper::createFolderIfNoExist(PATH_HOME . "uploads");
    \Helpers\Helper::createFolderIfNoExist(PATH_HOME . "uploads/tmp");

    $isImage = preg_match('/^image/i', $fileType);
    $dir = "uploads/tmp/" . $name . "." . ($isImage ? 'webp' : $extensao);
    file_put_contents(PATH_HOME . $dir, $file_data);

    $data['data']['url'] = HOME . $dir;
    $data['data']['image'] = HOME . (preg_match('/^data:image\//i', $type) ? $dir : "assetsPublic/img/file.png");
    $data['data']['preview'] = "<img src='" . $data['data']['image'] . "' title='Imagem " . $name . "' class='left radius'/>";
} else {
    $data['data']['url'] = "";
}