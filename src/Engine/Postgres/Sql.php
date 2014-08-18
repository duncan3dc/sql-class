<?php

namespace duncan3dc\SqlClass\Engine\Postgres;

use duncan3dc\Helpers\Helper;
use duncan3dc\SqlClass\Engine\AbstractSql;
use duncan3dc\SqlClass\NotImplementedException;
use duncan3dc\SqlClass\Result;

class Sql extends AbstractSql
{
    public function connect(array $options)
    {
        $connect = "host=" . $options["hostname"] . " ";
        $connect .= "user=" . $options["username"] . " ";
        $connect .= "password=" . $options["password"] . " ";
        $connect .= "dbname= " . $options["database"] . " ";
        return pg_connect($connect, \PGSQL_CONNECT_FORCE_NEW);
    }


    public function query($query, array $params = null, $preparedQuery)
    {
        $tmpQuery = $query;
        $query = "";

        $i = 1;
        reset($params);
        while ($pos = strpos($tmpQuery, "?")) {
            $query .= substr($tmpQuery, 0, $pos) . "\$" . $i++;
            $tmpQuery = substr($tmpQuery, $pos + 1);
        }
        $query .= $tmpQuery;

        $params = Helper::toArray($params);
        return pg_query_params($this->server, $query, $params);
    }


    public function functions(&$query)
    {
        $query = preg_replace("/\bI[FS]NULL\(/", "COALESCE(", $query);
        $query = preg_replace("/\bSUBSTR\(/", "SUBSTRING(", $query);
        $query = preg_replace("/\FROM_UNIXTIME\(([^,\)]+),(\s*)([^\)]+)\)/", "TO_CHAR(ABSTIME($1), $3)", $query);
    }


    public function limit(&$query)
    {
        $query = preg_replace("/\bFETCH\s+FIRST\s+([0-9]+)\s+ROW(S?)\s+ONLY\b/i", "\nLIMIT $1\n", $query);
    }


    protected function quoteTable($table)
    {
        $this->connect();
        return pg_escape_identifier($this->server, $table);
    }


    public function quoteValue($string)
    {
        return pg_escape_literal($this->server, $value);
    }


    public function getError()
    {
        return pg_last_error($this->server);
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

        $this->query("COPY " . $table . " (" . $fields . ") FROM STDIN");

        foreach ($params as $row) {
            if (!pg_put_line($this->server, implode("\t", $row) . "\n")) {
                $this->error();
            }
        }

        if (pg_put_line($this->server, "\\.\n")) {
            $this->error();
        }

        $result = new Result(pg_end_copy($this->server), $this->mode);
    }


    public function getId(Result $result)
    {
        return pg_last_oid($result);
    }


    public function startTransaction()
    {
        return $this->query("SET AUTOCOMMIT = OFF");
    }


    public function endTransaction()
    {
        return $this->query("SET AUTOCOMMIT = ON");
    }


    public function commit()
    {
        return $this->query("COMMIT");
    }


    public function rollback()
    {
        return $this->query("ROLLBACK");
    }


    public function lockTables(array $tables)
    {
        return $this->query("LOCK TABLE " . implode(",", $tables) . " IN EXCLUSIVE MODE");
    }


    public function unlockTables()
    {
        return $this->query("COMMIT");
    }


    public function getDatabases()
    {
        $databases = [];

        $result = $this->query("SHOW DATABASES");

        $result->fetchStyle(self::FETCH_ROW);
        foreach ($result as $row) {
            $databases[] = $row[0];
        }

        return $databases;
    }


    public function getTables($database)
    {
        $tables = [];

        $query = "SHOW FULL TABLES IN " . $this->quoteTable($database) . " WHERE table_type='BASE TABLE'";
        $result = $this->query($query);

        $result->fetchStyle(self::FETCH_ROW);
        foreach ($result as $row) {
            $tables[] = $row[0];
        }

        return $tables;
    }


    public function getViews($database)
    {
        $views = [];

        $query = "SHOW FULL TABLES IN " . $this->quoteTable($database) . " WHERE table_type='VIEW'";
        $result = $this->query($query);

        $result->fetchStyle(self::FETCH_ROW);
        foreach ($result as $row) {
            $views[] = $row[0];
        }

        return $views;
    }


    public function disconnect()
    {
        return pg_close($this->server);
    }
}
