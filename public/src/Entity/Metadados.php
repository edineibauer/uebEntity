<?php

namespace Entity;

use \Helpers\Helper;
use \Config\Config;

class Metadados
{
    /**
     * @param string|null $entity
     * @param bool|null $keepId
     * @param bool|null $keepStrings
     * @return array|null
     */
    public static function getDicionario(string $entity = null, bool $keepId = null, bool $keepStrings = null)
    {
        if (empty($entity)) {
            $list = [];
            foreach (Helper::listFolder(PATH_HOME . "entity/cache") as $entity) {
                if ($entity !== "info" && preg_match("/\.json$/i", $entity)) {
                    $entidade = str_replace(".json", "", $entity);
                    $list[$entidade] = self::getDicionario($entidade);
                }
            }
            return $list;

            //Se existir o dicionário da entidade
        } elseif (file_exists(PATH_HOME . "entity/cache/{$entity}.json")) {
            $data = json_decode(file_get_contents(PATH_HOME . "entity/cache/{$entity}.json"), !0);

            if ($keepId) {
                $data[0] = self::generatePrimary();
                $default = \EntityUi\InputType::getInputDefault();
                $inputType = \EntityUi\InputType::getInputTypes();

                $info = self::getInfo($entity);
                if (!empty($info['user']) && $info['user'] === 1)
                    $data["999997"] = self::generateUser($default, $inputType);

                if (!empty($info['autor'])) {
                    if ($info['autor'] === 1)
                        $data["999998"] = array_replace_recursive($default, $inputType['publisher'], ["indice" => 999998]);
                    elseif ($info['autor'] === 2)
                        $data["999999"] = array_replace_recursive($default, $inputType['owner'], ["indice" => 999999]);
                }
            } elseif (isset($data[0])) {
                unset($data[0]);
            }

            if (!$keepStrings) {
                foreach ($data as $i => $datum) {
                    if ($datum['key'] === 'information')
                        unset($data[$i]);
                }
            }

            $dicionario = [];
            foreach (Helper::convertStringToValueArray($data) as $id => $metas) {
                if (!empty($metas['allow']['options']))
                    $metas['allow']['options'] = array_reverse($metas['allow']['options']);

                $dicionario[$id] = $metas;
            }

            return $dicionario;
        }

        return null;
    }

    /**
     * @param array|null $default
     * @param array|null $inputType
     * @return mixed
     */
    public static function generateUser(array $default = null, array $inputType = null)
    {
        if(empty($default))
            $default = \EntityUi\InputType::getInputDefault();
        if(empty($inputType))
            $inputType = \EntityUi\InputType::getInputTypes();

        $mode = Helper::arrayMerge($default, $inputType['list']);
        $mode['nome'] = "Usuário Acesso Vínculo";
        $mode['column'] = "usuarios_id";
        $mode['form'] = "false";
        $mode['datagrid'] = "false";
        $mode['unique'] = "false";
        $mode['update'] = "true";
        $mode['size'] = "";
        $mode['minimo'] = "";
        $mode['relation'] = "usuarios";
        $mode['indice'] = "999997";

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
            "datagrid" => ["grid_relevant" => "", "grid_class" => "", "grid_style" => "", "grid_template" => ""],
            "select" => [],
            "filter" => []
        ];
    }

    /**
     * @param string $entity
     * @return mixed
     */
    public static function getInfo($entity)
    {
        if (file_exists(PATH_HOME . "entity/cache/info/{$entity}.json"))
            return Helper::convertStringToValueArray(json_decode(file_get_contents(PATH_HOME . "entity/cache/info/{$entity}.json"), !0));

        return null;
    }
}