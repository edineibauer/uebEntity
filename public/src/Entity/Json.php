<?php

namespace Entity;

use Helpers\Helper;
use Helpers\Check;

class Json extends VersionControl
{
    private $id;
    private $file;
    private $versionamento = true;

    /**
     * Json constructor.
     * @param string $folder
     * @param int $versionNumberControl
     */
    public function __construct(string $folder = "store", int $versionNumberControl = 2)
    {
        $folder = str_replace(PATH_HOME, '', Check::name($folder, ["/"]));
        $folder = (preg_match('/^\//i', $folder) ? substr($folder, 1) : $folder);
        $folder = (preg_match('/\/$/i', $folder) ? substr($folder, 0, -1) : $folder);

        parent::__construct($folder, $versionNumberControl);

        $dir = "_cdn";
        foreach (explode('/', parent::getFolder()) as $item) {
            $dir .= "/{$item}";
            Helper::createFolderIfNoExist(PATH_PRIVATE . $dir);
        }
    }

    /**
     * @param bool $versionamento
     */
    public function setVersionamento(bool $versionamento)
    {
        $this->versionamento = $versionamento;
    }

    /**
     * @param string $file
     * @return array
     */
    public function get(string $file): array
    {
        $this->setFile($file);
        $id = Check::name(pathinfo($file, PATHINFO_FILENAME));
        try {
            if (file_exists($this->file)) {
                $file = json_decode(file_get_contents($this->file), !0);
                if(is_array($file))
                    return array_merge(["id" => $id], $file);
            }
        } catch (\Exception $e) {
            return [];
        }
        return [];
    }

    /**
     * Cria ou Atualiza arquivo
     *
     * @param $id
     * @param array $data
     */
    public function save($id, array $data)
    {
        if(!empty($id) && (is_string($id) || is_numeric($id))) {
            $this->setFile($id);
            if ($this->file) {
                if (file_exists($this->file)) {
                    $this->update($id, $data);
                } else {
                    $this->add($id, $data);
                }
            }
        }
    }

    /**
     * Adiciona arquivo Json
     *
     * @param string $id
     * @param array $data
     * @return bool
     */
    public function add(string $id, array $data = []): bool
    {
        try {
            $this->setFile($id);
            if ($this->file) {

                if (!file_exists($this->file)) {
                    //Novo
                    if($this->versionamento) {
                        $data['created'] = strtotime("now");
                        $data['updated'] = strtotime("now");
                        parent::deleteVerion($this->file);
                    }

                    $f = fopen($this->file, "w+");
                    fwrite($f, json_encode($data));
                    fclose($f);

                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Atualiza arquivo Json
     *
     * @param string $id
     * @param array $dadosUpdate
     * @param int $recursiveVersion
     * @return bool
     */
    public function update(string $id, array $dadosUpdate, int $recursiveVersion = 99): bool
    {
        $this->setFile($id);
        if ($this->file && file_exists($this->file)) {

            if(isset($dadosUpdate['updated']))
                $dadosUpdate['updated'] = strtotime("now");
            $dadosAtuais = $this->get($id);

            unset($dadosAtuais['id']);

            if ($this->versionamento) {
                $dadosAtuais['userlogin-action'] = "update";
                parent::createVerion($this->file, $dadosAtuais, $recursiveVersion);
            }

            $f = fopen($this->file, "w+");
            fwrite($f, json_encode(Helper::arrayMerge($dadosAtuais, $dadosUpdate)));
            fclose($f);

            return true;
        } else {
            return false;
        }
    }

    /**
     * Deleta um arquivo json
     *
     * @param string $id
     */
    public function delete(string $id)
    {
        $this->setFile($id);
        if (file_exists($this->file)) {
            if ($this->versionamento) {
                $dadosAtuais = $this->get($id);
                $dadosAtuais['userlogin-action'] = "delete";
                unset($dadosAtuais['id']);
                parent::createVerion($this->file, $dadosAtuais);
            }

            if(file_exists($this->file))
                unlink($this->file);
        }
    }

    /**
     * Obtém os dados de uma versão anterior
     *
     * @param string $id
     * @param int $version
     * @return array
     */
    public function getVersion(string $id, int $version = 1): array
    {
        $this->setFile($id);
        $id = Check::name(pathinfo($id, PATHINFO_FILENAME));
        try {
            $fileName = str_replace("{$id}.json", "version/{$id}#{$version}.json", $this->file);
            if (file_exists($fileName))
                return array_merge(["id" => $id], json_decode(file_get_contents($fileName), true));
        } catch (\Exception $e) {
            return [];
        }
        return [];
    }

    /**
     * Recupera uma versão anterior
     *
     * @param string $id
     * @param int $version
     */
    public function rollBack(string $id, int $version = 1)
    {

    }

    /**
     * Seta o caminho do arquivo Json a ser trabalhado
     *
     * @param mixed $id
     */
    private function setFile(string $id)
    {
        if (!$this->id || $id != $this->id) {
            $this->id = $id;
            $id = Check::name($id, ["#"]);
            $this->file = (preg_match("/^" . preg_quote(PATH_HOME, '/') . "/i", $id) ? $id : PATH_PRIVATE . "_cdn/" . parent::getFolder() . "/{$id}");

            // Verifica se é final .json
            if (!preg_match("/\.json$/i", $id))
                $this->file .= ".json";
        }
    }
}