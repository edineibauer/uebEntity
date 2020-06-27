<?php

namespace Entity;

use Conn\Create;
use Conn\Delete;
use Conn\Read;
use Conn\Update;
use Helpers\Helper;
use Helpers\Check;
use Login\Social;
use Conn\SqlCommand;

class Dicionario
{
    private $entity;
    private $dataUpdate;
    private $defaultMeta;
    private $dicionario = [];
    private $info;
    private $relevant;

    /**
     * @param string $entity
     */
    public function __construct(string $entity)
    {
        $this->entity = $entity;
        $this->defaultMeta = \EntityUi\InputType::getInputDefault();

        if (!empty($this->entity) && file_exists(PATH_HOME . "entity/cache/{$this->entity}.json")) {
            $this->defaultDicionarioData();
        } else {
            echo "Entidade '{$this->entity}' não existe";
        }
    }

    /**
     * Seta valores para as Metas do Dicionário
     * valores aceitos: Array de valores, id para buscar os valores no banco, ou uma meta com valor
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->dataUpdate = $data;
        $this->defaultDicionarioData();

        // ID de dados na tabela
        if (!empty($data)) {
            if (is_numeric($data)) {
                $read = new Read();
                $read->exeRead($this->entity, "WHERE id = :id", "id={$data}");
                if ($read->getResult()) {
                    $dataRead = $read->getResult()[0];
                    foreach ($this->getAssociationMult() as $meta)
                        $dataRead[$meta->getColumn()] = $this->readMultValues($meta, $dataRead['id']);

                    $this->setDataArray($dataRead);
                } else {
                    $this->search(0)->setError("Id não existe");
                }

            } elseif (is_array($data)) {
                if (!empty($data[0]) && is_object($data[0]) && get_class($data[0]) === "Entity\Meta") {
                    $metas = $data;
                    $data = [];
                    foreach ($metas as $meta) {
                        if (!empty($meta->getValue()) && !empty($meta->getColumn()))
                            $data[$meta->getColumn()] = $meta->getValue();
                    }
                }

                if (!empty($data))
                    $this->setDataArray($data);
            }

            Validate::dicionario($this);
        }
    }

    /**
     * Busca por valores multiplos
     *
     * @param Meta $m
     * @param int $id
     * @return array
     */
    private function readMultValues(Meta $m, int $id): array
    {
        $data = [];
        $read = new Read();
        $read->exeRead(PRE . $this->entity . "_" . $m->getColumn(), "WHERE {$this->entity}_id = :id", "id={$id}");
        if ($read->getResult()) {
            foreach ($read->getResult() as $item)
                $data[] = $item[$m->getRelation() . "_id"];
        }
        return $data;
    }

    /**
     * Retorna o valor de uma meta de uma entidade
     *
     * @param string $column
     * @return mixed
     */
    public function getDataFullRead(string $column = null)
    {
        if (!empty($column))
            return (is_int($column) && isset($this->dicionario[$column]) ? $this->dicionario[$column]->getValue() : (!empty($value = $this->dicionario[$this->searchValue($column)]) ? $value->getValue() : null));

        $data = null;
        foreach ($this->dicionario as $meta) {
            if ($meta->getFormat() === "source" && preg_match('/"type": "image\//i', $meta->getValue())) {
                $data[$meta->getColumn()] = Helper::convertImageJson($meta->getValue());
            } elseif ($meta->getFormat() === "source") {
                $data[$meta->getColumn()] = json_decode($meta->getValue(), true)[0];
            } elseif (in_array($meta->getKey(), ["list", "selecao", "extend", "extend_add", "checkbox_rel"])) {
                $data[$meta->getColumn()] = Entity::read($meta->getRelation(), $meta->getValue());
            } elseif (in_array($meta->getFormat(), ["list_mult", "selecao_mult", "extend_mult", "checkbox_mult"])) {
                if (!empty($meta->getValue())) {
                    $lista = [];
                    foreach (json_decode($meta->getValue(), true) as $id) {
                        $lista[] = Entity::read($meta->getRelation(), $id);
                    }
                    $data[$meta->getColumn()] = $lista;
                }
            } elseif ($meta->getType() === "json") {
                $data[$meta->getColumn()] = (is_string($meta->getValue()) ? json_decode($meta->getValue(), true) : (is_array($meta->getValue()) ? $meta->getValue() : []));
            } else {
                $data[$meta->getColumn()] = $meta->getValue();
            }
        }

        return $data;
    }

    /**
     * Retorna os valores do dicionárioo de forma a preencher as necessidades do Form Crud
     *
     * @param string $column
     * @return mixed
     */
    public function getDataForm()
    {
        $data = null;
        foreach ($this->dicionario as $meta)
            $data[$meta->getColumn()] = $meta->getValue();

        return $data;
    }

    /**
     * Retorna valores de uma entidade correspondente ao seu armazenamento em sql na sua tabela
     *
     * @return array
     */
    private function getDataOnlyEntity(): array
    {
        $data = [];
        foreach ($this->dicionario as $meta) {
            if ($meta->getFormat() !== "information" && ($meta->getFormat() !== "password" || mb_strlen($meta->getValue(), 'utf8') > 3))
                $data[$meta->getColumn()] = ($meta->getValue() === "" ? null : $meta->getValue());
        }
        return $data;
    }


    /**
     * @return string
     */
    public function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        $error = null;
        foreach ($this->dicionario as $m) {
            if (!empty($m->getError()))
                $error[$this->entity][$m->getColumn()] = $m->getError();
        }
        return $error;
    }

    public function getExtends()
    {
        if (!$this->info)
            $this->info = Metadados::getInfo($this->entity);

        $data = [];
        foreach (["extend", "extend_add"] as $e) {
            if (!empty($this->info[$e])) {
                foreach ($this->info[$e] as $mult)
                    $data[] = $this->dicionario[$mult];
            }
        }

        return $data;
    }

    public function getNotAssociation()
    {
        $data = null;
        foreach ($this->dicionario as $i => $item) {
            if (!in_array($item->getKey(), ["extend", "list", "extend_add", "selecao", "checkbox_rel", "extend_mult", "list_mult", "selecao_mult", "checkbox_mult", "publisher"]))
                $data[] = $this->dicionario[$i];
        }
        return $data;
    }

    /**
     * @return array
     */
    public function getAssociationSimple(): array
    {
        if (!$this->info)
            $this->info = Metadados::getInfo($this->entity);

        $data = [];
        foreach (["extend", "list", "extend_add", "selecao", "checkbox_rel"] as $e) {
            if (!empty($this->info[$e])) {
                foreach ($this->info[$e] as $simple)
                    $data[] = $this->dicionario[$simple];
            }
        }
        return $data;
    }

    /**
     * @return array
     */
    public function getAssociationMult(): array
    {
        if (!$this->info)
            $this->info = Metadados::getInfo($this->entity);

        $data = [];
        foreach (["extend_mult", "list_mult", "selecao_mult", "checkbox_mult"] as $e) {
            if (!empty($this->info[$e])) {
                foreach ($this->info[$e] as $mult)
                    $data[] = $this->dicionario[$mult];
            }
        }
        return $data;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        if (!$this->info)
            $this->info = Metadados::getInfo($this->entity);

        return $this->dicionario[$this->info['title']] ?? null;
    }

    /**
     * @return mixed
     */
    public function getPublisher()
    {
        if (!$this->info)
            $this->info = Metadados::getInfo($this->entity);

        return $this->dicionario[$this->info['publisher']] ?? null;
    }

    /**
     * @return mixed
     */
    public function getDicionario()
    {
        return $this->dicionario;
    }

    /**
     * @return mixed
     */
    public function getInfo()
    {
        if (!$this->info)
            $this->info = Metadados::getInfo($this->entity);

        return $this->info;
    }

    public function getRelevant()
    {
        if (!$this->relevant)
            $this->setRelevants();

        return $this->relevant;
    }

    public function getListas()
    {
        if (!$this->info)
            $this->info = Metadados::getInfo($this->entity);

        $data = null;
        foreach (["extend_mult", "list", "extend_add", "selecao", "checkbox_rel", "list_mult", "selecao_mult", "checkbox_mult"] as $e) {
            if (!empty($this->info[$e])) {
                foreach ($this->info[$e] as $indice)
                    $data[] = $this->dicionario[$indice];
            }
        }
        return $data;
    }

    /**
     * @param mixed $attr
     * @param mixed $value
     * @return mixed
     */
    public function search($attr, $value = null)
    {
        $result = null;

        if (!$value) {
            //busca por indice ou por coluna
            $result = is_int($attr) ? $this->dicionario[$attr] ?? null : $this->dicionario[$this->searchValue($attr)] ?? null;

            //caso não tenha encontrado busca por key format
            if (!$result) {
                foreach ($this->dicionario as $i => $item) {
                    if ($item->getKey() === $attr || $item->getFormat() === $attr) {
                        $result = $item;
                        break;
                    }
                }
            }
        } else {

            //busca específica
            foreach ($this->dicionario as $item) {
                $metaColumn = $item->get($attr);
                if (is_array($metaColumn) && is_array($value)) {
                    $valid = true;
                    foreach ($value as $name => $v) {
                        if (!isset($metaColumn[$name]) || $metaColumn[$name] !== $v)
                            $valid = false;
                    }

                    if ($valid) {
                        $result = $item;
                        break;
                    }
                } elseif ($metaColumn === $value) {
                    $result = $item;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @param string $semantic
     * @return mixed
     */
    public function searchSemantic(string $semantic)
    {
        return $this->search($this->getInfo()[$semantic]);
    }

    /**
     * Deleta uma Meta do dicionário,
     * aceita indice, column ou Meta como parâmentro
     *
     * @param mixed $column
     */
    public function removeMeta($meta)
    {
        $indice = is_numeric($meta) ? ($this->dicionario[$meta] ?? null) : (is_object($meta) && get_class($meta) === "Entity\Meta" ? $this->searchValue($meta->getColumn()) : (is_string($meta) ? $this->searchValue($meta) : null));
        if ($indice)
            unset($this->dicionario[$indice]);
    }

    private function checkDataToSave($id)
    {
        if (!$this->getError() || !empty($id))
            $this->saveAssociacaoSimples();

        if (!$this->info)
            $this->info = Metadados::getInfo($this->entity);

        //verifica se possui owner ou autor
        if (!empty($this->info['autor']) && ($this->info['autor'] === 1 || $this->info['autor'] === 2)) {
            if (empty($id))
                $this->dicionario[($this->info['autor'] === 1 ? "999998" : "999999")]->setValue((!empty($_SESSION['userlogin']['id']) ? $_SESSION['userlogin']['id'] : null), !1);
            else
                unset($this->dicionario[($this->info['autor'] === 1 ? "999998" : "999999")]);
        }

        foreach ($this->dicionario as $i => $meta) {
            if ($meta->getType() === "json")
                $this->dicionario[$i]->setValueDirect(!empty($meta->getValue()) ? json_encode($meta->getValue()) : null);
        }

    }

    /**
     * Salva os dados do dicionário no banco de dados ou atualiza se for o caso
     */
    public function save()
    {
        $id = (int)$this->search(0)->getValue();
        $passCheck = $this->search("format", "passwordRequired");
        if ($passCheck) {
            $d = new Dicionario("usuarios");
            $columnPass = $d->search($d->getInfo()['password'])->getColumn();
        }
        if (!$passCheck || $passCheck->getValue() === $_SESSION['userlogin'][$columnPass]) {

            $this->checkDataToSave($id);

            $dadosEntity = $this->getDataOnlyEntity();
            if (!empty($id)) {
                /**
                 * Update
                 */

                //Remove metas com erros, metas que não podem ser atualizadas e metas que não pertencem ao escopo
                $fieldsUpdate = array_keys($this->dataUpdate);
                foreach ($this->dicionario as $meta) {
                    if ($meta->getError() || !$meta->getUpdate() || !in_array($meta->getColumn(), $fieldsUpdate)) {
                        unset($dadosEntity[$meta->getColumn()]);
                        if(isset($this->getError()[$this->entity][$meta->getColumn()]))
                            $meta->setError("");
                    }
                }

                if (!$this->getError()) {
                    $dadosEntity['id'] = $id;
                    $dadosEntity = $this->createUpdateUser($this->entity, $dadosEntity, "update");
                    if (!$this->getError())
                        $data = $this->updateTableData($id, $dadosEntity);
                }

            } else {

                /**
                 * Create
                 */
                if (!$this->getError()) {
                    $dadosEntity = $this->createUpdateUser($this->entity, $dadosEntity, "create");
                    if (!$this->getError())
                        $data = $this->createTableData($dadosEntity);
                }
            }

            if (!$this->getError() || !empty($id)) {

                $dadosEntity['id'] = (int)$this->search(0)->getValue();

                if (!empty($data['error'])) {
                    if (is_array($data['error'])) {
                        foreach ($data['error'] as $column => $err) {
                            if (is_string($column) && is_string($err) && $meta = $this->search($column)) {
                                $meta->setError($err);
                            }
                        }
                    } else {
                        $this->search(0)->setError($data['error']);
                    }
                } else {
                    $this->setData($this->search(0)->getValue());
                }
            }
        } else {
            $passCheck->setError("Senha Inválida");
        }
    }


    /**
     * @param string $entity
     * @param array $dados
     * @param string $action
     * @return array
     */
    private function createUpdateUser(string $entity, array $dados, string $action)
    {
        $info = Metadados::getInfo($entity);
        if (!empty($info['user']) && $info['user'] === 1) {

            /**
             * Obtém o ID de usuário
             */
            if ($action === "update") {
                $read = new Read();
                $read->exeRead($entity, "WHERE id = :id", "id={$dados['id']}");
                if ($read->getResult() && !empty($read->getResult()[0]['usuarios_id']))
                    $dados['usuarios_id'] = (int)$read->getResult()[0]['usuarios_id'];
            }

            $action = (empty($dados['usuarios_id']) ? "create" : $action);
            if ($action === "create") {
                $user = [
                    "nome" => "",
                    "password" => "",
                    "status" => 1,
                    "data" => date("Y-m-d H:i:s"),
                    "setor" => $entity,
                    "login_social" => 0
                ];
            } else {
                $user = [];
            }

            foreach (Metadados::getDicionario($entity) as $i => $meta) {
                if ($meta['format'] === "title")
                    $user['nome'] = $dados[$meta['column']];
                elseif ($meta['format'] === "password" && !empty($dados[$meta['column']]))
                    $user['password'] = $dados[$meta['column']];
                elseif ($meta['format'] === "status" && isset($dados[$meta['column']]))
                    $user['status'] = $dados[$meta['column']];
                elseif ($meta['format'] === "source_list" && in_array(["valor" => "png", "representacao" => "png"], $meta['allow']['options']) && isset($dados[$meta['column']]))
                    $user['imagem'] = $dados[$meta['column']];
            }

            /**
             * Define the social field
             */
            $facebookId = Social::facebookGetId($_SESSION['userlogin']['token']);
            if(!empty($facebookId) && Check::password($facebookId) === $user['password'] && defined('FACEBOOKENTITY') && $this->entity === FACEBOOKENTITY)
                $user['login_social'] = 2;

            $googleId = Social::googleGetId($_SESSION['userlogin']['token']);
            if(!empty($googleId) && Check::password($googleId) === $user['password'] && defined('GOOGLEENTITY') && $this->entity === GOOGLEENTITY)
                $user['login_social'] = 1;

            $read = new Read();
            if ($action === "create" && !empty($user['password']) && !empty($user['nome'])) {
                $read->exeRead("usuarios", "WHERE nome = '{$user['nome']}' && password = :p", "p={$user['password']}");
                if (!$read->getResult()) {
                    $create = new Create();
                    $create->exeCreate("usuarios", $user);
                    if ($create->getResult())
                        $dados['usuarios_id'] = $user['id'] = (int)$create->getResult();
                } else {
                    $dados['usuarios_id'] = $read->getResult()[0]['id'];
                }

            } elseif ($action === "update" && !empty($user)) {
                $sql = new SqlCommand();
                $sql->exeCommand("SELECT * FROM " . PRE . "usuarios WHERE id = {$dados['usuarios_id']}");
                if ($sql->getResult()) {
                    $newData = Helper::arrayMerge($sql->getResult()[0], $user);
                    $read->exeRead("usuarios", "WHERE nome = '{$newData['nome']}' && password = :p && id !=:id", "p={$newData['password']}id={$dados['usuarios_id']}");
                    if (!$read->getResult()) {
                        $up = new Update();
                        $up->exeUpdate("usuarios", $newData, "WHERE id = :ui", "ui={$dados['usuarios_id']}");
                        $newData['id'] = $dados['usuarios_id'];
                    }
                }
            }
        }

        return $dados;
    }

    /**
     * @param int $id
     * @param array $dadosEntity
     * @return |null
     */
    private function updateTableData(int $id, array $dadosEntity)
    {
        if (!empty($dadosEntity)) {
            $up = new Update();
            $up->exeUpdate($this->entity, $dadosEntity, "WHERE id = :id", "id={$id}");
            if ($up->getErro())
                $this->search(0)->setError($up->getErro());
            else
                return $up->getReact();
        }

        return null;
    }

    /**
     * @param array $dadosEntity
     * @return mixed
     */
    private function createTableData(array $dadosEntity)
    {
        unset($dadosEntity['id']);
        $create = new Create();
        $create->exeCreate($this->entity, $dadosEntity);
        if ($create->getErro()) {
            $this->search(0)->setError($create->getErro());
        } elseif ($create->getResult()) {
            $this->search(0)->setValue((int)$create->getResult(), !1);
            return $create->getReact();
        }
    }

    /**
     * @param int $id
     */
    /* private function checkToSetOwnerList(int $id)
     {
         $general = json_decode(file_get_contents(PATH_HOME . "entity/general/general_info.json"), true);
         if (!empty($general[$this->entity]['owner']) || !empty($general[$this->entity]['ownerPublisher'])) {
             foreach (array_merge($general[$this->entity]['owner'] ?? [], $general[$this->entity]['ownerPublisher'] ?? []) as $item) {
                 $entityRelation = $item[0];
                 $column = $item[1];
                 $userColumn = $item[2];

                 $read = new Read();
                 $read->exeRead($entityRelation, "WHERE {$userColumn} = :user", "user={$_SESSION['userlogin']['id']}");
                 if ($read->getResult()) {
                     $idUser = $read->getResult()[0]['id'];

                     $tableRelational = PRE . $entityRelation . "_" . $column;
                     $read->exeRead($tableRelational, "WHERE {$entityRelation}_id = :ai && {$this->entity}_id = :bi", "ai={$idUser}&bi={$id}");
                     if (!$read->getResult()) {

                         $create = new Create();
                         $create->exeCreate($tableRelational, [$entityRelation . "_id" => $idUser, $this->entity . "_id" => $id]);
                     }
                 }
             }
         }
     }*/

    private function saveAssociacaoSimples()
    {
        foreach ($this->getAssociationSimple() as $metaOne) {
            $d = $metaOne->getValue();
            if (!empty($d) && is_object($d) && get_class($d) === "Entity\Dicionario") {
                $d->save();

                if (!empty($d->getError()))
                    $this->dicionario[$metaOne->getId()]->setError($d->getError()[$d->getEntity()]);

                if (!empty($d->search(0)->getValue()))
                    $this->dicionario[$metaOne->getId()]->setValue((int)$d->search(0)->getValue(), !1);
            }
        }
    }

    private function createRelationalData()
    {
        $create = new Create();
        $del = new Delete();
        $id = $this->search(0)->getValue();
        foreach ($this->getAssociationMult() as $meta) {
            if (!empty($meta->getValue())) {
                $entityRelation = PRE . $this->entity . "_" . $meta->getColumn();
                $del->exeDelete($entityRelation, "WHERE {$this->entity}_id = :eid", "eid={$id}");
                $listId = [];
                foreach (json_decode($meta->getValue(), true) as $idRelation) {
                    if (!in_array($idRelation, $listId)) {
                        $listId[] = $idRelation;
                        $create->exeCreate($entityRelation, [$this->entity . "_id" => $id, $meta->getRelation() . "_id" => $idRelation]);
                    }
                }
            }
        }
    }

    /**
     * @param array $data
     */
    private function setDataArray(array $data)
    {
        foreach ($data as $column => $value) {
            if (is_numeric($column) && isset($this->dicionario[$column]))
                $this->dicionario[$column]->setValue($value);
            elseif (isset($this->dicionario[$field = $this->searchValue($column)]))
                $this->dicionario[$field]->setValue($value);
        }
    }

    private function searchValue($field, $search = null)
    {
        if (!$search) {
            $search = $field;
            $field = "column";
        }
        $results = array_keys(array_filter($this->dicionario, function ($value) use ($field, $search) {
            return $value->get($field) === $search;
        }));
        return (!empty($results) ? $results[0] : null);
    }

    /**
     * Seta default meta values to dicionario estrutura
     */
    private function defaultDicionarioData()
    {
        $this->dicionario = [];
        if ($dicEntity = Metadados::getDicionario($this->entity, !0, !0)) {
            foreach ($dicEntity as $i => $meta)
                $this->dicionario[$i] = new Meta(array_merge(['id' => $i], $meta), $this->defaultMeta);
        }
    }

    private function setRelevants()
    {
        foreach (\EntityUi\InputType::getInputRelevant() as $item) {
            if ($m = $this->search("format", $item)) {
                $this->relevant = $m;
                break;
            }
        }
    }
}