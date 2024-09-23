<?php

namespace Entity;

abstract class VersionControl
{
    private $folder;
    private $backup;

    /**
     * VersionControl constructor.
     * @param string $folder
     * @param int $versionNumberControl
     */
    public function __construct(string $folder, int $versionNumberControl)
    {
        $this->folder = $folder;
        $this->backup = $versionNumberControl ?? (defined("BACKUP") ? BACKUP : 2);
    }

    /**
     * @return string
     */
    protected function getFolder(): string
    {
        return $this->folder;
    }

    /**
     * Cria uma Versão do arquivo
     *
     * @param string $file
     * @param array $data
     * @param int $recursiveVersion
     */
    protected function createVerion(string $file, array $data, int $recursiveVersion = 99)
    {
        list($id, $folder) = $this->getBaseInfo($file);
        $idVersion = $this->getLastVersion(PATH_PRIVATE . "_cdn/{$folder}/{$id}", $recursiveVersion);

        $json = new Json($folder, 20);
        $json->setVersionamento(false);
        $json->add($id . "#{$idVersion}", $data);

        $this->updateChangeTimeEntity($folder);
    }

    /**
     * Cria um comando de exclusão
     *
     * @param string $file
     */
    protected function deleteVerion(string $file)
    {
        list($id, $folder) = $this->getBaseInfo($file);

        //Deleta qualquer versão existente
        for ($i = $this->backup; $i > 0; $i--) {
            if (file_exists(PATH_HOME . "{$this->folder}/{$folder}/version/{$id}#{$i}.json"))
                unlink(PATH_HOME . "{$this->folder}/{$folder}/version/{$id}#{$i}.json");
        }

        $json = new Json($folder);
        $json->setVersionamento(false);
        $json->add($id . "#{$this->backup}", ['userlogin-action' => "create"]);

        $this->updateChangeTimeEntity($folder);
    }

    /**
     * @param string $folder
     */
    private function updateChangeTimeEntity(string $folder)
    {
        if(preg_match('/\/version$/i', $folder))
            $folder = substr($folder, 0, -8);

        $part = explode('/', $folder);
        $entity = array_pop($part);
        $folder = implode('/', $part);

        $json = new Json($folder);
        $json->setVersionamento(false);

        $data = $json->get("historic");
        $data[$entity] = strtotime('now');

        $json->save("historic", $data);
    }

    /**
     * Retorna/Controla a versão mais atual
     *
     * @param string $url
     * @param int $recursiveVersion
     * @return int
     */
    private function getLastVersion(string $url, int $recursiveVersion = 99): int
    {
        $recursiveVersion++;
        for ($idVersion = $this->backup; $idVersion > 0; $idVersion--) {
            if (!file_exists("{$url}#{$idVersion}.json")) {
                break;
            } elseif ($idVersion === 1) {
                //chegou ao limite e não encontrou vaga.
                //Rename files to remove last and free first
                for ($i = $this->backup; $i > 1; $i--) {
                    if ($i < $recursiveVersion)
                        rename("{$url}#" . ($i - 1) . ".json", "{$url}#{$i}.json");
                }
                break;
            }
        }

        return $idVersion;
    }

    /**
     * Obtém os dados da url
     *
     * @param string $file
     * @return array
     */
    private function getBaseInfo(string $file)
    {
        $id = pathinfo($file, PATHINFO_FILENAME);
        $dir = pathinfo($file, PATHINFO_DIRNAME);
        $folder = str_replace(PATH_PRIVATE . '_cdn/', '', $dir) . "/version";

        \Helpers\Helper::createFolderIfNoExist($dir . "/version");

        return [$id, $folder];
    }

}