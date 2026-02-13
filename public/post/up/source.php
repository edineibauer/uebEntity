<?php

$data = ['error' => 0, 'data' => [], 'response' => 1];

$name = strip_tags(trim(filter_input(INPUT_POST, 'name', FILTER_DEFAULT)));
$fileType = strip_tags(trim(filter_input(INPUT_POST, 'fileType', FILTER_DEFAULT)));
$extensao = strip_tags(trim(filter_input(INPUT_POST, 'type', FILTER_DEFAULT)));
$source = strip_tags(trim(filter_input(INPUT_POST, 'url', FILTER_DEFAULT)));

$isImage = preg_match('/^image/i', $fileType);
$dir = "uploads/tmp/" . $name . "." . $extensao;

\Helpers\Helper::createFolderIfNoExist(PATH_HOME . "uploads");
\Helpers\Helper::createFolderIfNoExist(PATH_HOME . "uploads/tmp");

// Validar extensão antes de qualquer processamento
try {
    $allowedExtensions = \Security\FileUploadSecurity::getAllAllowedExtensions();
    $extensaoLower = strtolower($extensao);
    if (!in_array($extensaoLower, $allowedExtensions)) {
        $data['response'] = 2;
        $data['error'] = "Tipo de arquivo não permitido: .{$extensao}";
        $data['data'] = ['url' => ''];
        return;
    }

    // Verificar double extension e extensões perigosas no nome
    \Security\FileUploadSecurity::getSecureExtension(PATH_HOME . $dir, $name . "." . $extensao);
} catch (\Exception $e) {
    $data['response'] = 2;
    $data['error'] = $e->getMessage();
    $data['data'] = ['url' => ''];
    return;
}

if(!empty($_FILES['upload'])) {
    $file_data = $_FILES['upload'];

    // Validar tamanho (25MB máximo)
    if ($file_data['size'] > 25 * 1024 * 1024) {
        $data['response'] = 2;
        $data['error'] = "Arquivo muito grande. Máximo permitido: 25MB";
        $data['data'] = ['url' => ''];
        return;
    }

    move_uploaded_file( $file_data['tmp_name'], PATH_HOME . $dir);

} elseif (!empty($source) && is_string($source) && preg_match('/;/i', $source)) {
    list($type, $dd) = explode(';', $source);
    list(, $dd) = explode(',', $dd);
    $file_data = base64_decode(str_replace(' ', "+", $dd));

    // Validar tamanho do conteúdo decodificado
    if (strlen($file_data) > 25 * 1024 * 1024) {
        $data['response'] = 2;
        $data['error'] = "Arquivo muito grande. Máximo permitido: 25MB";
        $data['data'] = ['url' => ''];
        return;
    }

    file_put_contents(PATH_HOME . $dir, $file_data);
}

if(isset($file_data)) {
    // Validar conteúdo real do arquivo após salvar
    try {
        \Security\FileUploadSecurity::validateFile(PATH_HOME . $dir, $name . "." . $extensao, 25);
    } catch (\Exception $e) {
        // Remover arquivo inválido
        if (file_exists(PATH_HOME . $dir)) {
            unlink(PATH_HOME . $dir);
        }
        $data['response'] = 2;
        $data['error'] = $e->getMessage();
        $data['data'] = ['url' => ''];
        return;
    }

    $data['data']['url'] = HOME . $dir;
    if($isImage) {
        $data['data']['image'] = HOME . $dir;
        $data['data']['preview'] = "<img src='" . $data['data']['image'] . "' alt='' title='Imagem " . $name . "' class='left radius'/>";
    } else {
        $icon = (in_array($extensao, ["doc", "docx", "csv", "pdf", "xls", "xlsx", "ppt", "pptx", "zip", "rar", "search", "txt", "json", "js", "iso", "css", "html", "xml", "mp3", "csv", "psd", "mp4", "svg", "avi"]) ? $extensao : "file");
        $data['data']['image'] = HOME . "assetsPublic/img/file.png";
        $data['data']['preview'] = "<svg class='icon svgIcon' ><use xlink:href='#{$icon}'></use></svg>";
    }
} else {
    $data['data']['url'] = "";
}
