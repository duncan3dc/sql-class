<?php

namespace duncan3dc\SqlClass\Engine\Redshift;

use duncan3dc\SqlClass\Result;

class Sql extends \duncan3dc\SqlClass\Engine\Postgres
{

    public function query($query, array $params = null, $preparedQuery)
    {
        $tmpQuery = $query;
        $query = "";

        if (count($params) > 32767) {
            $noParams = true;
        } else {
            $noParams = false;
        }

        $i = 1;
        reset($params);
        while ($pos = strpos($tmpQuery, "?")) {
            if ($noParams) {
                $query .= substr($tmpQuery, 0, $pos) . "'" . pg_escape_string(current($params)) . "'";
                next($params);
            } else {
                $query .= substr($tmpQuery, 0, $pos) . "\$" . $i++;
            }
            $tmpQuery = substr($tmpQuery, $pos + 1);
        }
        $query .= $tmpQuery;

        $params = Helper::toArray($params);
        return pg_query_params($this->server, $query, $params);
    }


    public function bulkInsert($table, array $params, $extra = null)
    {
        $fields = "";
        $first = reset($params);
        foreach ($first as $key => $val) {
            if ($fields) {
                $fields .= ",";
            }
            $fields .= $this->quoteField($key);
        }

        $newParams = [];
        $noParams = false;
        if (count($params) * count($first) > 32767) {
            $noParams = true;
        }
        $values = "";

        foreach ($params as $row) {
            if ($values) {
                $values .= ",";
            }
            $values .= "(";
            $first = true;

            foreach ($row as $key => $val) {
                if ($first) {
                    $first = false;
                } else {
                    $values .= ",";
                }
                if ($noParams) {
                    $values .= "'" . pg_escape_string($val) . "'";
                } else {
                    $values .= "?";
                    $newParams[] = $val;
                }
            }
            $values .= ")";
        }

        $query = "INSERT INTO " . $table . " (" . $fields . ") VALUES " . $values;

        return $this->query($query, $newParams);
    }


    public function getId(Result $result)
    {
        throw new \Exception("getId() not available in this mode");
    }


    public function startTransaction()
    {
        return $this->query("START TRANSACTION");
    }


    public function endTransaction()
    {
        return true;
    }
}
