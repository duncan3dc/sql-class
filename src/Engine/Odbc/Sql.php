<?php

namespace duncan3dc\SqlClass\Engine\Odbc;

use duncan3dc\Helpers\Helper;
use duncan3dc\SqlClass\Engine\AbstractSql;
use duncan3dc\SqlClass\Exceptions\NotImplementedException;
use duncan3dc\SqlClass\Result;

class Sql extends AbstractSql
{
    public function connect(array $options)
    {
        return odbc_connect($options["hostname"], $options["username"], $options["password"]);
    }


    public function query($query, array $params = null, $preparedQuery)
    {
        if (!$result = odbc_prepare($this->server, $query)) {
            $this->error();
        }
        $params = Helper::toArray($params);
        if (!odbc_execute($result, $params)) {
            $this->error();
        }
    }


    public function changeQuerySyntax($query)
    {
        $query = preg_replace("/\bISNULL\(/", "IFNULL(", $query);
        $query = preg_replace("/\bSUBSTR\(/", "SUBSTRING(", $query);
        $query = preg_replace("/\bLIMIT\s+([0-9]+)\b/i", "\nFETCH FIRST $1 ROWS ONLY\n", $query);
        return $query;
    }


    public function quoteTable($table)
    {
        # The odbc sql only uses it's quote strings for renaming fields, not for quoting table/field names
        return $table;
    }


    public function quoteField($field)
    {
        # The odbc sql only uses it's quote strings for renaming fields, not for quoting table/field names
        return $field;
    }


    public function quoteValue($value)
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }


    public function getErrorCode()
    {
        return odbc_error($this->server);
    }


    public function getErrorMessage()
    {
        if ($this->server) {
            return odbc_errormsg($this->server);
        }
        return odbc_errormsg();
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
                $values .= "?";
                $newParams[] = $val;
            }
            $values .= ")";
        }

        $query = "INSERT INTO " . $table . " (" . $fields . ") VALUES " . $values;

        return $this->query($query, $newParams);
    }


    public function getId(Result $result)
    {
        throw new NotImplementedException("getId() not available in this mode");
    }


    public function getDatabases()
    {
        throw new NotImplementedException("getDatabases() not available in this mode");
    }


    public function getTables($database)
    {
        throw new NotImplementedException("getTables() not available in this mode");
    }


    public function getViews($database)
    {
        throw new NotImplementedException("getViews() not available in this mode");
    }


    public function startTransaction()
    {
        return odbc_autocommit($this->server, false);
    }


    public function endTransaction()
    {
        return odbc_autocommit($this->server, true);
    }


    public function commit()
    {
        return odbc_commit($this->server);
    }


    public function rollback()
    {
        return odbc_rollback($this->server);
    }


    public function lockTables(array $tables)
    {
        foreach ($tables as $table) {
            $this->query("LOCK TABLE " . $table . " IN EXCLUSIVE MODE ALLOW READ");
        }

        # If none of the locks failed then report success
        return true;
    }


    public function unlockTables()
    {
        return $this->query("COMMIT");
    }


    public function disconnect()
    {
        odbc_close($this->server);
        return true;
    }


    /**
     * Don't automatically close odbc connections, as odbc_connect() re-uses connections with the same credentials
     * So closing here could affect another instance of the sql class
     */
    public function __destruct()
    {
    }
}
