<?php

namespace duncan3dc\SqlClass\Engine\Mssql;

use duncan3dc\SqlClass\Engine\AbstractSql;
use duncan3dc\SqlClass\Exceptions\NotImplementedException;
use duncan3dc\SqlClass\Result;

class Sql extends AbstractSql
{
    public function connect(array $options)
    {
        return mssql_connect($options["hostname"], $options["username"], $options["password"]);
    }


    public function query($query, array $params = null, $preparedQuery)
    {
        return mssql_query($preparedQuery, $this->server);
    }


    public function functions(&$query)
    {
        $query = preg_replace("/\bIFNULL\(/", "ISNULL(", $query);
        $query = preg_replace("/\bSUBSTR\(/", "SUBSTRING(", $query);
    }


    public function limit(&$query)
    {
        return;
    }


    public function quoteTable($table)
    {
        return "[" . $table . "]";
    }


    public function quoteField($field)
    {
        return "[" . $field . "]";
    }


    public function quoteValue($value)
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }


    public function getErrorMessage()
    {
        return mssql_get_last_message();
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


    public function getId(Result $result)
    {
        throw new NotImplementedException("getId() not available in this mode");
    }


    public function getDatabases()
    {
        $databases = [];

        $result = $this->query("SELECT name FROM master..sysdatabases");
        foreach ($result as $row) {
            $databases[] = $row["name"];
        }

        return $databases;
    }


    public function getTables($database)
    {
        $tables = [];

        $query = "SELECT name FROM " . $this->quoteTable($database) . ".sys.tables";
        $result = $this->query($query);

        foreach ($result as $row) {
            $tables[] = $row["name"];
        }

        return $tables;
    }


    public function getViews($database)
    {
        $views = [];

        $query = "SELECT name FROM " . $this->quoteTable($database) . ".sys.views";
        $result = $this->query($query);

        foreach ($result as $row) {
            $views[] = $row["name"];
        }

        return $views;
    }


    public function startTransaction()
    {
        throw new NotImplementedException("startTransaction() not available in this mode");
    }


    public function endTransaction()
    {
        throw new NotImplementedException("endTransaction() not available in this mode");
    }


    public function commit()
    {
        throw new NotImplementedException("commit() not available in this mode");
    }


    public function rollback()
    {
        throw new NotImplementedException("rollback() not available in this mode");
    }


    public function lockTables(array $tables)
    {
        throw new NotImplementedException("lockTables() not available in this mode");
    }


    public function unlockTables()
    {
        throw new NotImplementedException("unlockTables() not available in this mode");
    }


    public function disconnect()
    {
        if (!$this->server) {
            return;
        }

        return mssql_close($this->server);
    }
}
