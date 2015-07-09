<?php

namespace duncan3dc\SqlClass\Engine\Postgres;

use duncan3dc\Helpers\Helper;
use duncan3dc\SqlClass\Engine\AbstractServer;
use duncan3dc\SqlClass\Exceptions\NotImplementedException;
use duncan3dc\SqlClass\Result as ResultInterface;

class Server extends AbstractServer
{
    /**
     * @var string $hostname The host or ip address of the database server.
     */
    protected $hostname;

    /**
     * @var string $username The user to authenticate with.
     */
    protected $username;

    /**
     * @var string $password The password to authenticate with.
     */
    protected $password;

    /**
     * @var string $database The database to use.
     */
    protected $database;


    /**
     * Create a new instance.
     *
     * @param string $hostname The host or ip address of the database server
     * @param string $username The user to authenticate with
     * @param string $password The password to authenticate with
     * @param string $database The database to use
     */
    public function __construct($hostname, $username, $password, $database)
    {
        $this->hostname = $hostname;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
    }

    /**
     * Get the quote characters that this engine uses for quoting identifiers.
     *
     * @return string
     */
    public function getQuoteChars()
    {
        return '"';
    }


    public function connect()
    {
        $connect = "host=" . $this->hostname . " ";
        $connect .= "user=" . $this->username . " ";
        $connect .= "password=" . $this->password . " ";
        $connect .= "dbname= " . $this->database . " ";
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

        if ($result = pg_query_params($this->server, $query, $params)) {
            return new Result($result);
        }
    }


    public function changeQuerySyntax($query)
    {
        $query = preg_replace("/\bI[FS]NULL\(/", "COALESCE(", $query);
        $query = preg_replace("/\bSUBSTR\(/", "SUBSTRING(", $query);
        $query = preg_replace("/\FROM_UNIXTIME\(([^,\)]+),(\s*)([^\)]+)\)/", "TO_CHAR(ABSTIME($1), $3)", $query);
        $query = preg_replace("/\bFETCH\s+FIRST\s+([0-9]+)\s+ROW(S?)\s+ONLY\b/i", "\nLIMIT $1\n", $query);
        return $query;
    }


    public function quoteTable($table)
    {
        $this->connect();
        return pg_escape_identifier($this->server, $table);
    }


    public function quoteField($field)
    {
        return "`" . $field . "`";
    }


    public function quoteValue($value)
    {
        return pg_escape_literal($this->server, $value);
    }


    public function getErrorMessage()
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

        $this->sql->query("COPY {$table} ({$fields}) FROM STDIN");

        foreach ($params as $row) {
            if (!pg_put_line($this->server, implode("\t", $row) . "\n")) {
                return;
            }
        }

        if (pg_put_line($this->server, "\\.\n")) {
            return;
        }

        return new Result(pg_end_copy($this->server));
    }


    public function getId(ResultInterface $result)
    {
        return pg_last_oid($result);
    }


    public function startTransaction()
    {
        return $this->sql->query("SET AUTOCOMMIT = OFF");
    }


    public function endTransaction()
    {
        return $this->sql->query("SET AUTOCOMMIT = ON");
    }


    public function commit()
    {
        return $this->sql->query("COMMIT");
    }


    public function rollback()
    {
        return $this->sql->query("ROLLBACK");
    }


    public function lockTables(array $tables)
    {
        return $this->sql->query("LOCK TABLE " . implode(",", $tables) . " IN EXCLUSIVE MODE");
    }


    public function unlockTables()
    {
        return $this->sql->query("COMMIT");
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


    public function disconnect()
    {
        return pg_close($this->server);
    }
}
