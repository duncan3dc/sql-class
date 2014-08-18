<?php

namespace duncan3dc\SqlClass\Engine;

use duncan3dc\SqlClass\Result;

abstract class AbstractSql
{
    protected $server;

    public function setServer($server)
    {
        $this->server = $server;
    }

    abstract public function connect(array $options);

    abstract public function query($query, array $params, $preparedQuery);

    abstract public function functions(&$query);

    abstract public function limit(&$query);

    abstract public function quoteValue($string);

    abstract public function getError();

    abstract public function bulkInsert($table, array $params, $extra = null);

    abstract public function getId(Result $result);

    abstract public function startTransaction();

    abstract public function endTransaction($commit);

    abstract public function commit();

    abstract public function rollback();

    abstract public function lockTables(array $tables);

    abstract public function unlockTables();

    abstract public function getDatabases();

    abstract public function getTables($database);

    abstract public function getViews($database);

    abstract public function disconnect();
}
