<?php

if(!empty($_SESSION['userlogin'])) {
    $dir = PATH_HOME . "_cdn/sync/" . $_SESSION['userlogin']['id'];

    if (file_exists($dir)) {
        foreach (\Helpers\Helper::listFolder($dir) as $item) {
            $data['data'][str_replace(".json", "", $item)] = json_decode(file_get_contents($dir . "/" . $item), !0);
            unlink($dir . "/" . $item);
        }
    }
}