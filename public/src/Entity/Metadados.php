<?php

namespace Entity;

use \Helpers\Helper;

class Metadados
{
    /**
     * @param string $entity
     * @param mixed $keepId
     * @return mixed
     */
    public static function getDicionario($entity, $keepId = null, $keepStrings = null)
    {
        $path = PATH_HOME . "entity/cache/" . $entity . '.json';
        $data = file_exists($path) ? json_decode(file_get_contents($path), true) : null;
        if ($data) {

            if($keepId) {
                $data[0] = self::generatePrimary();

                $info = self::getInfo($entity);
                if($info['user'] === 1)
                    $data["999997"] = self::generateUser();

                if(!empty($info['autor'])) {
                    $inputType = json_decode(file_get_contents(PATH_HOME . VENDOR . "entity-ui/public/entity/input_type.json"), true);

                    if($info['autor'] === 1)
                        $data["999998"] = array_replace_recursive($inputType['default'], $inputType['publisher'], ["indice" => 999998, "default" => $_SESSION['userlogin']['id']]);
                    elseif($info['autor'] === 2)
                        $data["999999"] = array_replace_recursive($inputType['default'], $inputType['owner'], ["indice" => 999999, "default" => $_SESSION['userlogin']['id']]);
                }
            }

            if(!$keepStrings) {
                foreach ($data as $i => $datum) {
                    if ($datum['key'] === 'information')
                        unset($data[$i]);
                }
            }

            return Helper::convertStringToValueArray($data);
        }

        return null;
    }

    public static function generateUser()
    {
        $types = json_decode(file_get_contents(PATH_HOME . VENDOR . "entity-ui/public/entity/input_type.json"), !0);
        $mode = Helper::arrayMerge($types["default"], $types['list']);
        $mode['nome'] = "Usuário Acesso Vínculo";
        $mode['column'] = "usuarios_id";
        $mode['form'] = "false";
        $mode['datagrid'] = "false";
        $mode['default'] = "false";
        $mode['unique'] = "false";
        $mode['update'] = "false";
        $mode['size'] = "";
        $mode['minimo'] = "";
        $mode['relation'] = "usuarios";
        $mode['indice'] = "999998";

        return $mode;
    }

    private static function generatePrimary()
    {
        return [
            "format" => "none",
            "type" => "int",
            "nome" => "id",
            "column" => "id",
            "size" => "",
            "key" => "identifier",
            "unique" => "true",
            "default" => "false",
            "update" => "false",
            "relation" => "",
            "minimo" => "",
            "allow" => [
                "regex" => "",
                "options" => "",
                "validate" => ""
            ],
            "form" => [
                "input" => "hidden",
                "cols" => "12",
                "colm" => "",
                "coll" => "",
                "class" => "",
                "style" => ""
            ],
            "select" => [],
            "filter" => []
        ];
    }

    /**
     * @param string $entity
     * @param mixed $mod
     * @return mixed
     */
    public static function getRelevant(string $entity, $mod = null)
    {
        $id = null;
        $info = self::getInfo($entity);
        foreach (self::getRelevantAll($entity) as $r) {
            if (isset($info[$r]) && !empty($info[$r]))
                return $mod ? [$info[$r], $r] : $info[$r];
        }

        return 0;
    }

    /**
     * @param string $entity
     * @return mixed
     */
    public static function getRelevantAll(string $entity)
    {
        if (file_exists(PATH_HOME . "entity/relevant/{$entity}.json"))
            return json_decode(file_get_contents(PATH_HOME . "entity/relevant/{$entity}.json"), true);
        else
            return json_decode(file_get_contents(PATH_HOME . VENDOR . "entity-ui/public/entity/relevant.json"), true);
    }

    /**
     * @param string $entity
     * @return mixed
     */
    public static function getInfo($entity)
    {
        $path = PATH_HOME . "entity/cache/info/" . $entity . '.json';
        $data = file_exists($path) ? json_decode(file_get_contents($path), true) : null;
        if ($data)
            return Helper::convertStringToValueArray($data);

        return null;
    }
}