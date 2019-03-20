<?php

namespace Entity;

use Conn\Read;
use Conn\TableCrud;
use Helpers\Check;

abstract class EntityDelete
{
    protected static $error;

    /**
     * Retorna o erro acumulado na Entidade
     *
     * @return mixed
     */
    public static function getError()
    {
        return self::$error;
    }

    /**
     * @param mixed $error
     */
    public static function setError($error)
    {
        self::$error = $error;
    }

    /**
     * Deleta informações de uma entidade
     *
     * @param string $entity
     * @param mixed $data
     * @param bool $checkPermission
     */
    protected static function exeDelete(string $entity, $data, bool $checkPermission)
    {
        if (is_int($data)) {
            $del = new TableCrud($entity);
            $del->load($data);
            if ($del->exist()) {
                if(Entity::checkPermission($entity, $data, $checkPermission)) {
                    $dados = $del->getDados();
                    self::deleteLinkedContent($entity, $dados);
                    $del->delete();
                } else {
                    self::$error[$entity]['id'] = "permissão negada";
                }
            } else {
                self::$error[$entity]['id'] = "id não encontrado para deletar";
            }
        } elseif (is_array($data)) {
            if (Check::isAssoc($data)) {
                $del = new TableCrud($entity);
                $del->loadArray($data);
                if ($del->exist()) {
                    if(Entity::checkPermission($entity, $del->getDados()['id'], $checkPermission)) {
                        $dados = $del->getDados();
                        self::deleteLinkedContent($entity, $dados);
                        $del->delete();
                    } else {
                        self::$error[$entity]['id'] = "permissão negada";
                    }
                } else {
                    self::$error[$entity]['id'] = "data não encontrada para deletar";
                }
            } else {
                foreach ($data as $datum)
                    self::exeDelete($entity, $datum, $checkPermission);
            }
        }
    }

    /**
     * Deleta informações extendidas multiplas de uma entidade
     *
     * @param string $entity
     * @param array $data
     */
    private static function deleteLinkedContent(string $entity, array $data)
    {
        $info = Metadados::getInfo($entity);
        $dic = Metadados::getDicionario($entity);
        if ($info && isset($data['id'])) {

            /*   REMOVE EXTEND MULT  */
            if ($info['extend_mult']) {
                foreach ($info['extend_mult'] as $item) {
                    $read = new Read();
                    $read->exeRead(PRE . $entity . "_" . $dic[$item]['relation'] . "_" . $dic[$item]['column'], "WHERE {$entity}_id = :id", "id={$data['id']}");
                    if ($read->getResult()) {
                        foreach ($read->getResult() as $i)
                            self::deleteExtend($dic[$item]['relation'], $i[$dic[$item]['relation'] . "_id"]);
                    }
                }
            }

            /*   REMOVE EXTEND   */
            if ($info['extend']) {
                foreach ($info['extend'] as $item)
                    self::deleteExtend($dic[$item]['relation'], $data[$dic[$item]['column']]);
            }

            /*   REMOVE SOURCES   */
            foreach ($dic as $id => $c) {
                if ($c['key'] === "source" && !empty($data[$c['column']])) {
                    $read = new Read();
                    $read->exeRead(PRE . $entity, "WHERE id != :id && " . $c['column'] . " = :s", "id={$data['id']}&s={$data[$c['column']]}");
                    if (!$read->getResult() && file_exists(PATH_HOME . $data[$c['column']]))
                        unlink(PATH_HOME . $data[$c['column']]);
                }
            }
        }
    }

    /**
     * Deleta informações extendidas de uma entidade
     *
     * @param string $entity
     * @param int $id
     */
    private static function deleteExtend(string $entity, int $id)
    {
        $del = new TableCrud($entity);
        $del->load($id);
        if ($del->exist())
            $del->delete();
    }
}