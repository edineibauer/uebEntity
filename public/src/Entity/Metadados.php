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
            if (!$keepId)
                unset($data[0]);

            if(!$keepStrings) {
                foreach ($data as $i => $datum) {
                    if ($datum['key'] === 'information')
                        unset($data[$i]);
                }
            }

            $info = Metadados::getInfo($entity);
            if(!empty($info['autor'])) {
                if($info['autor'] === 1) {
                    $inputType = json_decode(file_get_contents(PATH_HOME . VENDOR . "entity-ui/public/entity/input_type.json"), true);
                    $data[999999] = array_replace_recursive($inputType['default'], $inputType['publisher'], ["indice" => 999999, "default" => $_SESSION['userlogin']['id']]);
                } elseif($info['autor'] === 2) {
                    $inputType = json_decode(file_get_contents(PATH_HOME . VENDOR . "entity-ui/public/entity/input_type.json"), true);
                    $data[999999] = array_replace_recursive($inputType['default'], $inputType['owner'], ["indice" => 999999, "default" => $_SESSION['userlogin']['id']]);
                }
            }

            return Helper::convertStringToValueArray($data);
        }

        return null;
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