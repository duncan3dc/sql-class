<?php

namespace duncan3dc\SqlClass\Engine;

use duncan3dc\SqlClass\Exceptions\QueryException;
use duncan3dc\SqlClass\Result;

abstract class AbstractSql
{
    protected $server;

    public function setServer($server)
    {
        $this->server = $server;
    }


    public function getErrorCode()
    {
        return QueryException::UNKNOWN_ERROR;
    }


    /**
     * Automatically close the connection on destruction
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    abstract public function connect(array $options);

    abstract public function query($query, array $params, $preparedQuery);

    abstract public function changeQuerySyntax($query);

    abstract public function quoteTable($table);

    abstract public function quoteValue($string);

    abstract public function getErrorMessage();

    abstract public function bulkInsert($table, array $params, $extra = null);

    abstract public function getId(Result $result);

    abstract public function startTransaction();

    abstract public function endTransaction();

    abstract public function commit();

    abstract public function rollback();

    abstract public function lockTables(array $tables);

    abstract public function unlockTables();

    abstract public function getDatabases();

    abstract public function getTables($database);

    abstract public function getViews($database);

    abstract public function disconnect();
}
