<?php

namespace Entity;

use Conn\Read;
use Helpers\Check;
use Helpers\Helper;
use WideImage\WideImage;

class Meta
{
    private $id;
    private $allow;
    private $column;
    private $default;
    private $error;
    private $filter;
    private $form;
    private $datagrid;
    private $format;
    private $key;
    private $group;
    private $indice;
    private $nome;
    private $relation;
    private $select;
    private $size;
    private $type;
    private $unique;
    private $update;
    private $value;

    /**
     * Pode receber os dados em formato array das informações dessa Meta
     *
     * @param mixed $dados
     * @param mixed $default
     */
    public function __construct($dados = null, $default = null)
    {
        if ($dados)
            $this->setDados($dados, $default);
    }

    public function setValueDirect($value)
    {
        $this->value = $value;
    }

    /**
     * @param string $entity
     * @param $value
     * @return array
     */
    private function processaUploadsJson(string $entity, $value)
    {
        if (!empty($value) && !empty($entity)) {
            if (is_array($value) && !empty($value[0])) {
                $result = [];
                foreach ($value as $item) {
                    $d = new Dicionario($entity);
                    foreach ($d->getDicionario() as $i => $m) {
                        if ($m->key === "source" && !empty($item[$m->column])) {
                            $item[$m->column] = $this->uploadSource($item[$m->column]);
                        } else if($m->key === "relation" && $m->type === "json" && !empty($item[$m->column])){
                            $item[$m->column] = $this->processaUploadsJson($m->relation, $item[$m->column]);
                        }
                    }
                    $result[] = $item;
                }
                $value = $result;
                unset($result);
            } else {
                $d = new Dicionario($entity);
                foreach ($d->getDicionario() as $i => $m) {
                    if ($m->key === "source" && !empty($value[$m->column])) {
                        $value[$m->column] = $this->uploadSource($value[$m->column]);
                    } else if($m->key === "relation" && $m->type === "json"){
                        $value[$m->column] = $this->processaUploadsJson($m, $value[$m->column]);
                    }
                }
            }
        }
        return $value;
    }

    /**
     * @param mixed $value
     * @param bool $validate
     */
    public function setValue($value, bool $validate = true)
    {
        if ($validate)
            $this->error = null;

        if ($this->type === "json")
            $value = (Check::isJson($value) ? json_decode($value, true) : (is_array($value) || is_object($value) ? $value : null));
        elseif ($this->key === "publisher" && !empty($_SESSION['userlogin']))
            $value = (int)($_SESSION['userlogin']['setor'] == 1 ? (!empty($value) ? $value : $_SESSION['userlogin']['id']) : $_SESSION['userlogin']['id']);
        elseif ($this->key === "publisher")
            $this->error = "Precisa estar Logado";
        elseif ($this->group === "boolean")
            $value = $value ? 1 : 0;
        else
            $value = $value;

//        elseif (in_array($this->key, ["list", "selecao", "checkbox_rel"]))
//            $this->checkValueAssociacaoSimples($value);

        //dados relacionais em formato json
        if ($this->key === "relation" && $this->type === "json")
            $value = $this->processaUploadsJson($this->relation, $value);
        elseif ($this->key === "source" && !empty($value))
            $value = $this->uploadSource($value);

        $this->value = $value;

        if ($validate)
            Validate::meta($this);
    }

    /**
     * @param mixed $allow
     */
    public function setAllow($allow = null)
    {
        $content = ['regex', 'validate', 'options'];
        if ($allow) {
            $this->allow = $allow;
        } else {
            foreach ($content as $item)
                $this->allow[$item] = "";
        }
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = (int)$id;
    }

    /**
     * @param mixed $column
     */
    public function setColumn($column)
    {
        $this->column = $column;
    }

    /**
     * @param mixed $default
     */
    public function setDefault($default)
    {
        if ($default === "datetime")
            $this->default = "";
        elseif ($default === "date")
            $this->default = "";
        elseif ($default === "time")
            $this->default = "";
        else
            $this->default = $default;

    }

    /**
     * @param mixed $error
     */
    public function setError($error)
    {
        if (!$this->error || !$error)
            $this->error = $error;
    }

    /**
     * @param mixed $filter
     */
    public function setFilter($filter)
    {
        $this->filter = $filter;
    }

    /**
     * @param mixed $form
     */
    public function setForm($form = null)
    {
        if (!empty($form) && is_array($form)) {
            foreach ($form as $name => $value) {
                if (in_array($name, ['input', 'cols', 'atributos', 'template', 'coll', 'colm', 'class', 'style', 'defaults', 'fields']))
                    $this->form[$name] = $value;
            }
        } else {
            $this->form = false;
        }
    }

    /**
     * @param mixed $grid
     */
    public function setDatagrid($grid = null)
    {
        $default = array_keys($this->defaultDatagrid());
        if (!empty($grid) && is_array($grid)) {
            foreach ($grid as $name => $value) {
                if (in_array($name, $default))
                    $this->datagrid[$name] = $value;
            }
        } else {
            $this->datagrid = false;
        }
    }

    /**
     * @param string $format
     */
    public function setFormat(string $format)
    {
        $this->format = $format;
    }

    /**
     * @param mixed $key
     */
    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * @param mixed $indice
     */
    public function setIndice($indice)
    {
        $this->indice = $indice;
    }

    /**
     * @param mixed $group
     */
    public function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * @param mixed $nome
     */
    public function setNome($nome)
    {
        $this->nome = $nome;
    }

    /**
     * @param mixed $relation
     */
    public function setRelation($relation)
    {
        $this->relation = $relation;
    }

    /**
     * @param mixed $select
     */
    public function setSelect($select)
    {
        $this->select = $select;
    }

    /**
     * @param mixed $size
     */
    public function setSize($size)
    {
        $this->size = $size;
    }

    /**
     * @param string $type
     */
    public function setType(string $type)
    {
        $this->type = $type;
    }

    /**
     * @param bool $unique
     */
    public function setUnique(bool $unique)
    {
        $this->unique = $unique;
    }

    /**
     * @param bool $update
     */
    public function setUpdate(bool $update)
    {
        $this->update = $update;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return (int)$this->id;
    }

    /**
     * @return mixed
     */
    public function getAllow()
    {
        return $this->allow;
    }

    /**
     * @return mixed
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @return mixed
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @return mixed
     */
    public function getForm()
    {
        return $this->form ?? $this->defaultForm();
    }

    /**
     * @return mixed
     */
    public function getDatagrid()
    {
        return $this->datagrid ?? $this->defaultDatagrid();
    }

    /**
     * @return mixed
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return int
     */
    public function getIndice(): int
    {
        return (int)$this->indice;
    }

    /**
     * @return mixed
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @return mixed
     */
    public function getNome()
    {
        return $this->nome;
    }

    /**
     * @return mixed
     */
    public function getRelation()
    {
        return $this->relation;
    }

    /**
     * @return mixed
     */
    public function getSelect()
    {
        return $this->select;
    }

    /**
     * @return mixed
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getUnique()
    {
        return $this->unique;
    }

    /**
     * @return mixed
     */
    public function getUpdate()
    {
        return $this->update;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    public function get($name)
    {
        return $this->$name ?? null;
    }

    /**
     * @return mixed
     */
    public function getDados()
    {
        return [
            "id" => $this->id,
            "allow" => $this->allow,
            "column" => $this->column,
            "default" => $this->default,
            "filter" => $this->filter,
            "form" => $this->form,
            "datagrid" => $this->datagrid,
            "format" => $this->format,
            "key" => $this->key,
            "indice" => $this->indice,
            "group" => $this->group,
            "nome" => $this->nome,
            "relation" => $this->relation,
            "select" => $this->select,
            "size" => $this->size,
            "type" => $this->type,
            "unique" => $this->unique,
            "update" => $this->update,
            "value" => $this->value
        ];
    }

    /**
     * Informa dados a esta Meta
     *
     * @param mixed $dados
     * @param mixed $default
     */
    public function setDados($dados = null, $default)
    {
        if (!empty($dados)) {
            if (!$default)
                $default = json_decode(file_get_contents(PATH_HOME . VENDOR . "entity-ui/public/entity/input_type.json"), true)['default'];

            foreach (array_replace_recursive($default, $dados) as $dado => $value) {
                switch ($dado) {
                    case 'id':
                        $this->setId($value);
                        break;
                    case 'allow':
                        $this->setAllow($value);
                        break;
                    case 'column':
                        $this->setColumn($value);
                        break;
                    case 'default':
                        $this->setDefault($value);
                        break;
                    case 'update':
                        $this->setUpdate($value);
                        break;
                    case 'filter':
                        $this->setFilter($value);
                        break;
                    case 'form':
                        $this->setForm($value);
                        break;
                    case 'datagrid':
                        $this->setDatagrid($value);
                        break;
                    case 'format':
                        $this->setFormat($value);
                        break;
                    case 'key':
                        $this->setKey($value);
                        break;
                    case 'indice':
                        $this->setIndice($value);
                        break;
                    case 'group':
                        $this->setGroup($value);
                        break;
                    case 'nome':
                        $this->setNome($value);
                        break;
                    case 'relation':
                        $this->setRelation($value);
                        break;
                    case 'select':
                        $this->setSelect($value);
                        break;
                    case 'size':
                        $this->setSize($value);
                        break;
                    case 'type':
                        $this->setType($value);
                        break;
                    case 'unique':
                        $this->setUnique($value);
                        break;
                    case 'value':
                        $this->setValue($value);
                        break;
                }
            }
        }
    }

    /**
     * @param mixed $value
     */
    private function checkValueAssociacaoSimples($value)
    {
        if (!empty($value)) {
            if (is_numeric($value))
                $this->value = $value;
            elseif (is_array($value) && !isset($value[0]))
                $this->value = $this->getDicionarioFromDataExtend($value);
            else
                $this->error = "valor não esperado";
        }
    }

    /**
     * @param mixed $value
     */
    private function checkValueAssociacaoMult($value)
    {
        if (!empty($value)) {
            if (Check::isJson($value))
                $this->checkValueExtendMult(json_decode($value, true));
            elseif (is_array($value))
                $this->checkValueExtendMult($value);
            elseif (is_numeric($value))
                $this->value = json_encode([0 => $this->checkIdExist($value)]);
            else
                $this->error = "valor não esperado";
        }
    }

    /**
     * @param array $value
     */
    private function checkValueExtendMult(array $value)
    {
        if (isset($value[0])) {
            if (is_numeric($value[0])) {
                $this->value = $this->checkListIdExist($value);
            } elseif (is_array($value[0])) {
                //lista de dados extendido
                foreach ($value as $item) {
                    if (!isset($item[0]))
                        $this->value[] = $this->getIdFromDataExtend($item);
                    else
                        $this->error = "lista de valores não esperado";
                }
                $this->value = !empty($this->value) ? json_encode($this->value) : null;
            } else {
                $this->error = "valor não esperado para um campo do tipo {$this->key}";
            }
        } else {
            $this->value = json_encode([0 => $this->getIdFromDataExtend($value)]);
        }
    }

    /**
     * @param int $id
     * @return mixed
     */
    private function checkIdExist(int $id)
    {
        $read = new Read();
        $read->exeRead($this->relation, "WHERE id = :id", "id={$id}");
        return $read->getResult() ? (int)$id : null;
    }

    /**
     * @param array $listId
     * @return mixed
     */
    private function checkListIdExist(array $listId)
    {
        $newList = [];
        foreach ($listId as $id) {
            if ($idn = $this->checkIdExist($id))
                $newList[] = $idn;
            else
                $this->error = "um ou mais ids da lista não existe";
        }

        return !empty($newList) ? json_encode($newList) : null;
    }

    /**
     * @param array $data
     * @return mixed
     */
    private function getDicionarioFromDataExtend(array $data)
    {
        $d = new Dicionario($this->relation);
        $d->setData($data);
        if ($d->getError()) {
            $this->error = $d->getError()[$this->relation];
            if (empty($d->search(0)->getValue()))
                return null;
        }

        return $d;
    }

    /**
     * @param array $data
     * @return mixed
     */
    private function getIdFromDataExtend(array $data)
    {
        $return = Entity::add($this->relation, $data);
        if (!is_numeric($return)) {
            $this->error = $return[$this->relation];
            return null;
        }

        return (int)$return;
    }

    /**
     * @param array $value
     * @return array|mixed|string
     */
    private function uploadSource(array $value)
    {
        if (is_array($value)) {
            foreach ($value as $i => $item) {

                // Decode base64 data
                if (!empty($item['url']) && is_string($item['url']) && preg_match('/;/i', $item['url'])) {
                    list($type, $data) = explode(';', $item['url']);
                    list(, $data) = explode(',', $data);
                    $file_data = base64_decode(str_replace(' ', "+", $data));

                    Helper::createFolderIfNoExist(PATH_HOME . "uploads");
                    Helper::createFolderIfNoExist(PATH_HOME . "uploads/form");
                    Helper::createFolderIfNoExist(PATH_HOME . "uploads/form/" . date("Y"));
                    Helper::createFolderIfNoExist(PATH_HOME . "uploads/form/" . date("Y") . "/" . date("m"));

                    $dir = "uploads/form/" . date("Y") . "/" . date("m") . "/" . $item['name'] . "-" . strtotime('now') . '.' . $item['type'];
                    file_put_contents(PATH_HOME . $dir, $file_data);
                    $value[$i]['url'] = HOME . $dir;
                    $value[$i]['image'] = HOME . "image/" . (preg_match('/^data:image\//i', $type) ? $dir : "assetsPublic/img/file.png");

                } elseif (empty($item['url']) || !is_string($item['url'])) {
                    $value[$i]['url'] = null;
                    $value[$i]['image'] = HOME . "assetsPublic/img/file.png";
                }
            }
        }

        return $value;
    }

    /**
     * Retorna form padrão caso o campo não tenha form, mas tenha sido solicitado nos $this->fields
     * @return array
     */
    private function defaultForm(): array
    {
        $input = json_decode(file_get_contents(PATH_HOME . VENDOR . "entity-ui/public/entity/input_type.json"), true);
        return $input['default']['form'];
    }

    /**
     * Retorna grid padrão caso o campo não tenha grid, mas tenha sido solicitado nos $this->fields
     * @return array
     */
    private function defaultDatagrid(): array
    {
        $input = json_decode(file_get_contents(PATH_HOME . VENDOR . "entity-ui/public/entity/input_type.json"), true);
        return $input['default']['datagrid'];
    }
}