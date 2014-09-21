<?php

namespace duncan3dc\SqlClass\Engine\Sqlite;

use duncan3dc\Helpers\Helper;
use duncan3dc\SqlClass\Engine\AbstractSql;
use duncan3dc\SqlClass\Result as ResultInterface;
use duncan3dc\SqlClass\Sql as SqlClass;

class Sql extends AbstractSql
{
    public function connect(array $options)
    {
        return new \Sqlite3($options["database"]);
    }


    public function query($query, array $params = null, $preparedQuery)
    {
        if (!is_array($params)) {
            return $this->server->query($preparedQuery);
        }

        # If we have some parameters then we must convert them to the sqlite format
        $newQuery = "";
        foreach ($params as $key => $val) {
            $pos = strpos($query, "?");
            $newQuery .= substr($query, 0, $pos);
            $query = substr($query, $pos + 1);

            $newQuery .= ":var" . $key;
        }
        $newQuery .= $query;

        if (!$result = $this->server->prepare($newQuery)) {
            $this->error();
        }

        foreach ($params as $key => $val) {
            switch (gettype($val)) {

                case "boolean":
                case "integer":
                    $type = SQLITE3_INTEGER;
                    break;

                case "double":
                    $type = SQLITE3_FLOAT;
                    break;

                case "NULL":
                    if ($this->allowNulls) {
                        $type = SQLITE3_NULL;
                    } else {
                        $type = SQLITE3_TEXT;
                        $val = "";
                    }
                    break;

                default:
                    $type = SQLITE3_TEXT;
            }

            $result->bindValue(":var" . $key, $val, $type);
        }

        return $result->execute();
    }


    public function functions(&$query)
    {
        $query = preg_replace("/\bISNULL\(/", "IFNULL(", $query);
        $query = preg_replace("/\bSUBSTRING\(/", "SUBSTR(", $query);
    }


    public function limit(&$query)
    {
        $query = preg_replace("/\bFETCH\s+FIRST\s+([0-9]+)\s+ROW(S?)\s+ONLY\b/i", "\nLIMIT $1\n", $query);
    }


    public function quoteTable($table)
    {
        return "`" . $table . "`";
    }


    public function quoteField($field)
    {
        return "`" . $field . "`";
    }


    public function quoteValue($value)
    {
        return "'" . $this->server->escapeString($value) . "'";
    }


    public function getError()
    {
        return $this->server->lastErrorMsg() . " (" . $this->server->lastErrorCode() . ")";
    }


    public function bulkInsert($table, array $params, $extra = null)
    {
        foreach ($params as $newParams) {
            if (!$this->insert($table, $newParams)) {
                return false;
            }
        }
        return true;
    }


    public function getId(ResultInterface $result)
    {
        return $this->query("SELECT last_insert_rowid()")->fetch(SqlClass::FETCH_ROW)[0];
    }


    public function getDatabases()
    {
        throw new \Exception("getDatabases() not available in this mode");
    }


    public function getTables($database)
    {
        throw new \Exception("getTables() not available in this mode");
    }


    public function getViews($database)
    {
        throw new \Exception("getViews() not available in this mode");
    }


    public function startTransaction()
    {
        throw new \Exception("startTransaction() not available in this mode");
    }


    public function endTransaction()
    {
        throw new \Exception("endTransaction() not available in this mode");
    }


    public function commit()
    {
        throw new \Exception("commit() not available in this mode");
    }


    public function rollback()
    {
        throw new \Exception("rollback() not available in this mode");
    }


    public function lockTables(array $tables)
    {
        throw new \Exception("lockTables() not available in this mode");
    }


    public function unlockTables()
    {
        throw new \Exception("unlockTables() not available in this mode");
    }


    public function disconnect()
    {
        if (!$this->server) {
            return;
        }

        return $this->server->close();
    }
}
