<?php

namespace FpDbTest;

use Exception;
use mysqli;



class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    const STRING = 'string';
    const INT = 'integer';
    const FLOAT = 'float';
    const OBJECT = 'object';
    const BOOLEAN = 'boolean';
    const NULL = 'null';
    const ARRAY = 'array';

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * @throws Exception
     */
    public function buildQuery(string $query, array $args = []): string
    {
        $parameter = 0;
        while (substr_count($query, '?') > 0) {
            $index = strpos($query, '?');
            $ifindex = strpos($query, '{');
            if ($ifindex > 0 && $ifindex < $index) {
                $this->processStatement($query, $args[$parameter],$parameter);
            }
            if (isset($query[$index + 1])) {
                switch($query[$index + 1]) {
                    case 'a': {
                        $this->bindAssoc($query, $args[$parameter]);
                        $parameter++;
                        break;
                    }
                    case '#' : {
                        $this->bindIdentifiers($query, $args[$parameter]);
                        $parameter++;
                        break;
                    }
                    case 'd' : {
                        $this->bindInteger($query, $args[$parameter]);
                        $parameter++;
                        break;
                    }
                    case 'f' : {
                        $this->bindFloat($query, $args[$parameter]);
                        $parameter++;
                        break;
                    }
                    default : {
                        $this->bindDefault($query, $args[$parameter]);
                        $parameter++;
                        break;
                    }
                }
            }

        }
        return $query;
    }

    /**
     * @throws Exception
     */
    public function skip()
    {
        $parameter = new \stdClass();
        $parameter->skip = true;
        return $parameter;
    }

    private function removeBlocks(string &$query) {
        $query = str_replace("{", "", $query);
        $query = str_replace("}", "", $query);
    }
    private function replaceCondition(string &$query) {
        $query = preg_replace(
            "/[{](.*)[}]/", "", $query
        );
    }
    private function setEqualBinding(array $params): array
    {
        return array_map(function ($value, $key) {

            if (gettype($value) === 'string') {
                $value = "'$value'";
            }
            if (!$value) {
                $value = 'NULL';
            }

            return "`$key` = $value";
        }, array_values($params), array_keys($params));
    }
    private function bind(string &$string, string $search, $param): void
    {
        $string = implode(
            $param, explode($search, $string, 2)
        );
    }

    private function isCorrectType($param, string $pattern): bool
    {
        $type = gettype($param);
        switch ($pattern) {
            case '?a' : {
                return in_array($type, [self::ARRAY]);
            }
            case '?#': {
                return in_array($type,[self::ARRAY, self::STRING]);
            }
            default : {
                return in_array($type, [self::INT, self::STRING, self::FLOAT, self::BOOLEAN, self::NULL]);
            }
        }
    }

    private function processStatement(string &$query, $parameter, int &$indexOfParameter) {
        if (gettype($parameter) !== self::BOOLEAN && $parameter->skip) {
            $indexOfParameter++;
            $this->replaceCondition($query);
        } else {
            $this->removeBlocks($query);
        }
    }

    /**
     * @throws Exception
     */
    private function bindAssoc(string &$query, $parameter) {
        if ($this->isCorrectType([$parameter], '?a')) {
            if ($this->isAssoc($parameter)) {
                $strargs = $this->setEqualBinding($parameter);
                $this->bind($query, "?a", implode(', ', $strargs));
            } else {
                $this->bind($query, "?a",
                    implode(', ', $parameter)
                );
            }
        } else {
            throw new Exception('incorrect type for ?a binding');
        }
    }

    /**
     * @throws Exception
     */
    private function bindIdentifiers(string &$query, $parameter) {
        if ($this->isCorrectType($parameter, '?#')) {
            $strargs = '';
            if (gettype($parameter) === "string") {
                $strargs = "`$parameter`";
            } else {
                $strargs = implode(
                    ", ",
                    array_map(fn($arg) => "`$arg`", $parameter)
                );
            }
            $this->bind($query, "?#", $strargs);
        } else {
            throw new Exception('incorrect type of ?# binding');
        }
    }

    /**
     * @throws Exception
     */
    private function bindInteger(string &$query, $parameter) {
        if ($this->isCorrectType($parameter, '?d')) {
            $this->bind($query, "?d", (int)$parameter);
        } else {
            throw new Exception('incorrect type of ?d binding');
        }
    }

    /**
     * @throws Exception
     */
    private function bindFloat(string &$query, $parameter) {
        if ($this->isCorrectType($parameter, '?f')) {
            $this->bind($query, "?", (float)$parameter);
        } else {
            throw new Exception('incorrect type of ?f binding');
        }
    }

    /**
     * @throws Exception
     */
    private function bindDefault(string &$query, $parameter) {
        if ($this->isCorrectType($parameter, 'default')) {
            if (gettype($parameter) === 'string') {
                $this->bind($query, "?", "'$parameter'");
            } else {
                $this->bind($query, "?", $parameter);
            }
        } else {
            throw new Exception('invalid type of ? binding');
        }
    }

    private function isAssoc(array $array): bool
    {
        return count(array_filter(array_keys($array), 'is_string')) > 0;
    }

}
