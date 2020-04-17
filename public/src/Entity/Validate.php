<?php

namespace Entity;

use Conn\Read;
use Helpers\Check;

class Validate
{
    /**
     * Valida valor a ser inserido na meta
     *
     * @param Meta $m
     * @param $value
     * @return mixed
     */
    public static function meta(Meta $m)
    {
        if ($m->getColumn() !== "id") {
            self::checkDefaultSet($m);
            if (!empty($m->getValue()) && !in_array($m->getKey(), ["list", "selecao", "checkbox_rel"])) {
                self::checkRegular($m);
                self::convertValues($m);
                self::checkType($m);
                self::checkSize($m);
                self::checkValidate($m);
            }
        }
    }

    /**
     * @param Dicionario $d
     */
    public static function dicionario(Dicionario $d)
    {
        if (Entity::checkPermission($d->getEntity(), $d->search(0)->getValue())) {

            //Group User Check
            $groupUser = "";
            if(!empty($d->getInfo()['list'])) {
                foreach ($d->getInfo()['list'] as $item) {
                    $list = $d->search($item);
                    if(Metadados::getInfo($list->getRelation())['user'] === 2) {
                        $groupUser .= $list->getColumn() . " = " . $list->getValue();
                        break;
                    }
                }
            }

            foreach ($d->getDicionario() as $m) {
                if ($m->getColumn() !== "id" && !in_array($m->getKey(), ["list", "selecao", "checkbox_rel"])) {
                    self::checkLink($d, $m);
                    self::checkUnique($d, $m, $groupUser);

                    if ($m->getKey() === "link" && $m->getError()) {
                        $d->getRelevant()->setError($m->getError());
                        $m->setError(null);
                        $m->setValue(null, !1);
                    }

                    if ($m->getError())
                        $m->setValue(null, !1);
                }
            }

            foreach ($d->getDicionario() as $m)
                self::checkDefaultPattern($d, $m);

        } else {
            $d->search(0)->setError("Permissão Negada");
        }
    }

    /**
     * Verifica se o campo é do tipo link, se for, linka o valor ao título
     *
     * @param Dicionario $d
     * @param Meta $m
     */
    private static function checkLink(Dicionario $d, Meta $m)
    {
        if ($m->getKey() === "link") {
            if (!empty($d->getRelevant()->getValue()))
                $m->setValue(Check::name($d->getRelevant()->getValue()), !1);
        }
    }

    /**
     * Verifica se o valor precisa ser único
     *
     * @param Dicionario $d
     * @param Meta $m
     * @param string $groupUser
     */
    private static function checkUnique(Dicionario $d, Meta $m, string $groupUser)
    {
        if ($m->getUnique()) {
            $where = "WHERE {$m->getColumn()} = '{$m->getValue()}'" . (!empty($groupUser) ? " && {$groupUser}" : "");
            $where .= (!empty($d->search(0)->getValue()) ? " && id != " . $d->search("id")->getValue() : "");
            $read = new Read();
            $read->exeRead($d->getEntity(), $where);
            if ($read->getResult())
                $m->setError("Valor já existe, informe outro");
        }
    }

    /**
     * Verifica se o campo é do tipo link, se for, linka o valor ao título
     *
     * @param Dicionario $d
     * @param Meta $m
     */
    private static function checkDefaultPattern(Dicionario $d, Meta $m)
    {
        if (preg_match('/{\$/', $m->getDefault())) {
            $newDefault = "";
            $error = false;
            foreach (explode('{$', $m->getDefault()) as $i => $expressao) {
                if ($i > 0) {
                    $variable = explode('}', $expressao);
                    $base = $variable[1];
                    $variable = trim(strtolower($variable[0]));
                    $mod = null;
                    $param = null;
                    if (preg_match('/\|/i', $variable)) {
                        $mod = explode('|', $variable);
                        $variable = $mod[0];
                        $mod = $mod[1];
                        if (preg_match('/\(/i', $mod) && preg_match('/\)/i', $mod)) {
                            $param = explode('(', $mod);
                            $mod = $param[0];
                            $param = explode(')', $param[1])[0];
                            if (preg_match('/,/i', $param))
                                $param = explode(',', $param);
                        }
                    }

                    if ($value = self::getValueFromVarible($d, $variable))
                        $newDefault .= ($mod ? self::proccessFunction($value, $mod, $param) : $value);

                    $newDefault .= $base;

                } else {
                    $newDefault .= $expressao;
                }
            }

            $m->setValue($newDefault);
        }

    }

    private static function proccessFunction($value, $mod = null, $param = null)
    {
        switch ($mod) {
            case 'str_pad':
                return str_pad($value, !empty($param[0]) ? $param[0] : 2, !empty($param[1]) ? $param[1] : '0', !empty($param[2]) && $param[2] === "right" ? STR_PAD_RIGHT : STR_PAD_LEFT);
                break;
            default:
                return $value;
        }
    }

    /**
     * @param Dicionario $d
     * @param string $variable
     * @return mixed
     */
    private static function getValueFromVarible(Dicionario $d, string $variable)
    {
        if (preg_match('/./i', $variable)) {
            foreach ($variables = explode('.', $variable) as $i => $variable) {
                if ($m = $d->search($variable)) {
                    if ($m->getError()) {
                        return null;
                    } elseif ($i === count($variables) - 1) {
                        return !empty($m->getAllow()['names']) ? $m->getAllow()['names'][array_search($m->getValue(), $m->getAllow()['values'])] : $m->getValue();
                    } elseif (!empty($m->getRelation()) && !empty($m->getValue())) {
                        $d = new Dicionario($m->getRelation());
                        $d->setData($m->getValue());
                    } else {
                        return null;
                    }
                    //                } elseif (!empty($m->getSelect()) && in_array($variable, $m->getSelect())) {
                    //                    if ($meta = $d->search($variable . "__" . $variables[$i - 1])) {
                    //                        if (!empty($meta->getValue()) && !empty($meta->getRelation())) {
                    //                            $d = new Dicionario($meta->getRelation());
                    //                            $d->setData($meta->getValue());
                    //                        } else {
                    //                            return null;
                    //                        }
                    //                    } else {
                    //                        return null;
                    //                    }
                } else {
                    return null;
                }
            }

        } else {
            if ($m = $d->search($variable))
                return $m->getValue();
            else
                return null;
        }
    }

    /**
     * Verifica se precisa alterar de modo padrão a informação deste campo
     *
     * @param Meta $m
     * @param mixed $value
     */
    protected static function checkDefaultSet(Meta $m)
    {
        if ($m->getValue() === "")
            $m->setValue(null, !1);

        if ($m->getValue() === null) {
            if ($m->getDefault() === false) {
                if ($m->getDefault() === false)
                    $m->setError("Preencha este Campo");
                else
                    $m->setValue("", !1);
            } elseif (!empty($m->getDefault())) {
                $m->setValue($m->getDefault(), !1);

            }
        }
    }

    /**
     * Verifica se o tipo do campo é o desejado
     *
     * @param Meta $m
     */
    private static function convertValues(Meta $m)
    {
        if ($m->getType() === "json" && Check::isJson($m->getValue()))
            $m->setValue(json_decode($m->getValue(), true), !1);
        elseif ($m->getGroup() === "boolean")
            $m->setValue($m->getValue() === "true" || $m->getValue() === "1" || $m->getValue() === 1 || $m->getValue() === true ? 1 : 0, !1);

        if (!empty($m->getValue()) && $m->getFormat() === "password" || $m->getFormat() === "passwordRequired") {
            $m->setValue(Check::password($m->getValue()), !1);

        } elseif ($m->getFormat() === "valor") {
            $value = $m->getValue();

            if(is_float($value) || is_int($value))
                $value = (string) $value;

            $f = "";
            for($i=0; $i < strlen($value); $i ++) {
                if(is_numeric($value[$i]))
                    $f .= $value[$i];
                elseif($value[$i] === "." || $value[$i] === ",")
                    $f = str_replace([',', '.'], '', $f) . ".";
            }
            $value = (float) number_format((float) $f, 2, '.', '');
            $m->setValue($value, !1);
        }

        if ($m->getKey() === "link")
            $m->setValue(Check::name($m->getValue()), !1);
    }

    /**
     * Verifica se o tipo do campo é o desejado
     *
     * @param Meta $m
     */
    private static function checkType(Meta $m)
    {
        if (in_array($m->getType(), ["tinyint", "smallint", "mediumint", "int", "bigint"])) {
            if (!is_numeric($m->getValue())) {
                if ($m->getDefault() === false)
                    $m->setError("número inválido");
                else
                    $m->setValue("", !1);
            }

        } elseif (in_array($m->getType(), array("double", "real", "float"))) {
            if (!is_numeric($m->getValue())) {
                if ($m->getDefault() === false)
                    $m->setError("valor não é um número");
                else
                    $m->setValue("", !1);
            }

        } elseif (in_array($m->getType(), array("bit", "boolean", "serial"))) {
            if (!is_bool($m->getValue())) {
                if ($m->getDefault() === false)
                    $m->setError("valor boleano inválido. (true ou false)");
                else
                    $m->setValue("", !1);
            }

        } elseif (in_array($m->getType(), array("datetime", "timestamp"))) {
            if (!preg_match('/\d{4}-\d{2}-\d{2}[T\s]+\d{2}:\d{2}/i', $m->getValue())) {
                if ($m->getDefault() === false)
                    $m->setError("formato de data inválido ex válido:(2017-08-23 21:58:00)");
                else
                    $m->setValue("", !1);
            }

        } elseif ($m->getType() === "date") {
            if (!preg_match('/\d{4}-\d{2}-\d{2}/i', $m->getValue())) {
                if ($m->getDefault() === false)
                    $m->setError("formato de data inválido ex válido:(2017-08-23)");
                else
                    $m->setValue("", !1);
            }

        } elseif ($m->getType() === "time") {
            if (!preg_match('/\d{2}:\d{2}/i', $m->getValue())) {
                if ($m->getDefault() === false)
                    $m->setError("formato de tempo inválido ex válido:(21:58)");
                else
                    $m->setValue("", !1);
            }

        } elseif ($m->getType() === "json" && !is_array($m->getValue())) {
            if ($m->getDefault() === false)
                $m->setError("formato json inválido");
            else
                $m->setValue("", !1);
        }
    }

    /**
     * Verifica se o tamanho do valor corresponde ao desejado
     *
     * @param Meta $m
     */
    private static function checkSize(Meta $m)
    {
        if ($m->getSize() && in_array($m->getType(), ["varchar", "char", "tinytext", "text", "mediumtext", "longtext", "tinyint", "smallint", "mediumint", "int", "bigint"])) {
            $length = mb_strlen($m->getValue(), 'utf8');
            if ($m->getType() === "varchar" && $length > $m->getSize()) {
                if ($m->getDefault() === false)
                    $m->setError("tamanho máximo de caracteres excedido. Max {$m->getSize()}");
                else
                    $m->setValue($m->getDefault(), !1);
            } elseif ($m->getType() === "char" && $length > 1) {
                if ($m->getDefault() === false)
                    $m->setError("tamanho máximo de caracteres excedido. Max {$m->getSize()}");
                else
                    $m->setValue($m->getDefault(), !1);

            } elseif ($m->getType() === "tinytext" && ($length > 255 || $length > $m->getSize())) {
                if ($m->getDefault() === false)
                    $m->setError("tamanho máximo de caracteres excedido. Max {$m->getSize()}");
                else
                    $m->setValue($m->getDefault(), !1);

            } elseif ($m->getType() === "text" && ($length > 65535 || $length > $m->getSize())) {
                if ($m->getDefault() === false)
                    $m->setError("tamanho máximo de caracteres excedido. Max {$m->getSize()}");
                else
                    $m->setValue($m->getDefault(), !1);

            } elseif ($m->getType() === "mediumtext" && ($length > 16777215 || $length > $m->getSize())) {
                if ($m->getDefault() === false)
                    $m->setError("tamanho máximo de caracteres excedido. Max {$m->getSize()}");
                else
                    $m->setValue($m->getDefault(), !1);

            } elseif ($m->getType() === "longtext" && ($length > 4294967295 || $length > $m->getSize())) {
                if ($m->getDefault() === false)
                    $m->setError("tamanho máximo de caracteres excedido. Max {$m->getSize()}");
                else
                    $m->setValue($m->getDefault(), !1);

            } elseif ($m->getType() === "tinyint" && ($m->getValue() > $m->getSize())) {
                if ($m->getDefault() === false)
                    $m->setError("numero excedeu seu limite. Max " . $m->getSize());
                else
                    $m->setValue($m->getDefault(), !1);

            } elseif ($m->getType() === "smallint" && ($m->getValue() > $m->getSize())) {
                if ($m->getDefault() === false)
                    $m->setError("numero excedeu seu limite. Max " . $m->getSize());
                else
                    $m->setValue($m->getDefault(), !1);

            } elseif ($m->getType() === "mediumint" && ($m->getValue() > $m->getSize())) {
                if ($m->getDefault() === false)
                    $m->setError("numero excedeu seu limite. Max " . $m->getSize());
                else
                    $m->setValue($m->getDefault(), !1);

            } elseif ($m->getType() === "int" && !in_array($m->getKey(), ["list", "selecao", "checkbox_rel"]) && ($m->getValue() > $m->getSize())) {
                if ($m->getDefault() === false)
                    $m->setError("numero excedeu seu limite. Max " . $m->getSize());
                else
                    $m->setValue($m->getDefault(), !1);

            } elseif ($m->getType() === "bigint" && ($m->getValue() > $m->getSize())) {
                if ($m->getDefault() === false)
                    $m->setError("numero excedeu seu limite. Max " . $m->getSize());
                else
                    $m->setValue($m->getDefault(), !1);
            }
        }
    }

    /**
     * @param int $value
     * @return int
     */
    private static function intLength(int $value): int
    {
        return (int)(pow(2, ($value * 2)) - 1);
    }

    /**
     * Verifica se existe expressão regular, e se existe, aplica a verificação
     *
     * @param Meta $m
     */
    private static function checkRegular(Meta $m)
    {
        if (!empty($m->getAllow()['regex']) && is_string($m->getValue()) && !preg_match($m->getAllow()['regex'], $m->getValue())) {
            if ($m->getDefault() === false)
                $m->setError("formato não permitido.");
            else
                $m->setValue($m->getDefault(), !1);
        }
    }

    /**
     * Verifica se o campo precisa de validação pré-formatada
     *
     * @param Meta $m
     */
    private static function checkValidate(Meta $m)
    {
        if (!empty($m->getAllow()['validate'])) {
            if ($m->getAllow()['validate'] === "email" && !Check::email($m->getValue())) {
                if ($m->getDefault() === false)
                    $m->setError("email inválido.");
                else
                    $m->setValue($m->getDefault(), !1);

            } elseif ($m->getAllow()['validate'] === "cpf" && !Check::cpf($m->getValue())) {
                if ($m->getDefault() === false)
                    $m->setError("CPF inválido.");
                else
                    $m->setValue($m->getDefault(), !1);

            } elseif ($m->getAllow()['validate'] === "cnpj" && !Check::cnpj($m->getValue())) {
                if ($m->getDefault() === false)
                    $m->setError("CNPJ inválido.");
                else
                    $m->setValue($m->getDefault(), !1);
            }
        }
    }
}