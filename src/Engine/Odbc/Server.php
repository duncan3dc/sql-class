<?php

namespace duncan3dc\SqlClass\Engine\Odbc;

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
     * Check if this server supports the TRUNCATE TABLE statement.
     *
     * @return bool
     */
    public function canTruncateTables()
    {
        return false;
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
        return odbc_connect($this->hostname, $this->username, $this->password);
    }


    public function query($query, array $params = null, $preparedQuery)
    {
        if (!$result = odbc_prepare($this->server, $query)) {
            return;
        }

        $params = Helper::toArray($params);
        if (!odbc_execute($result, $params)) {
            return;
        }

        return new Result($result);
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


    public function getId(ResultInterface $result)
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
            $this->sql->query("LOCK TABLE {$table} IN EXCLUSIVE MODE ALLOW READ");
        }

        # If none of the locks failed then report success
        return true;
    }


    public function unlockTables()
    {
        return $this->sql->query("COMMIT");
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