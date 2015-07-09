<?php

namespace duncan3dc\SqlClass\Engine\Mssql;

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
     * Create a new instance.
     *
     * @param string $hostname The host or ip address of the database server
     * @param string $username The user to authenticate with
     * @param string $password The password to authenticate with
     */
    public function __construct($hostname, $username, $password)
    {
        $this->hostname = $hostname;
        $this->username = $username;
        $this->password = $password;
    }


    /**
     * Get the quote characters that this engine uses for quoting identifiers.
     *
     * @return string[]
     */
    public function getQuoteChars()
    {
        return ["[", "]"];
    }


    public function connect()
    {
        return mssql_connect($this->hostname, $this->username, $this->password);
    }


    public function query($query, array $params = null, $preparedQuery)
    {
        if ($result = mssql_query($preparedQuery, $this->server)) {
            return new Result($result);
        }
    }


    public function changeQuerySyntax($query)
    {
        $query = preg_replace("/\bIFNULL\(/", "ISNULL(", $query);
        $query = preg_replace("/\bSUBSTR\(/", "SUBSTRING(", $query);
        return $query;
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


    public function getId(ResultInterface $result)
    {
        throw new NotImplementedException("getId() not available in this mode");
    }


    public function getDatabases()
    {
        $databases = [];

        $result = $this->sql->query("SELECT name FROM master..sysdatabases");
        foreach ($result as $row) {
            $databases[] = $row["name"];
        }

        return $databases;
    }


    public function getTables($database)
    {
        $tables = [];

        $query = "SELECT name FROM " . $this->quoteTable($database) . ".sys.tables";
        $result = $this->sql->query($query);

        foreach ($result as $row) {
            $tables[] = $row["name"];
        }

        return $tables;
    }


    public function getViews($database)
    {
        $views = [];

        $query = "SELECT name FROM " . $this->quoteTable($database) . ".sys.views";
        $result = $this->sql->query($query);

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
