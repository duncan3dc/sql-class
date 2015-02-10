<?php

namespace duncan3dc\SqlClass\Engine;

use duncan3dc\SqlClass\Exceptions\QueryException;
use duncan3dc\SqlClass\Result;

abstract class AbstractSql
{
    /**
     * @var resource $server The resource for the current database connection
     */
    protected $server;


    /**
     * Set the current server to be used for queries.
     *
     * @param resource $server The resource (returned by the appropriate connect function)
     *
     * @return void
     */
    public function setServer($server)
    {
        $this->server = $server;
    }


    /**
     * Automatically close the connection on destruction.
     */
    public function __destruct()
    {
        $this->disconnect();
    }


    /**
     * Connect to the database using the supplied credentials.
     */
    abstract public function connect(array $options);


    /**
     * Run a query.
     *
     * @param string $query The query to run
     * @param array $params The parameters to substitute in the query string
     * @param string $preparedQuery A simulated prepared query (if the server doens't support prepared statements)
     *
     * @return Result|boolean SELECT statements will return a Result instance, while INSERT/DELETE/UPDATE statements will return a boolean
     */
    abstract public function query($query, array $params, $preparedQuery);


    /**
     * Convert any supported function for this database mode.
     *
     * eg, the sqlite class would replace SUBSTRING() with SUBSTR()
     *
     * @param string $query The query to manipulate
     *
     * @return string The modified query
     */
    abstract public function changeQuerySyntax($query);


    /**
     * Quote the supplied table with the relevant characters used by the database engine.
     *
     * @param string $table The table name
     *
     * @return string The quoted table name
     */
    abstract public function quoteTable($table);


    /**
     * Quote the supplied string with the relevant characters used by the database engine.
     *
     * @param string $table The string to quote
     *
     * @return string The quoted string
     */
    abstract public function quoteValue($string);


    /**
     * Get the error code of the last error.
     *
     * @return int
     */
    public function getErrorCode()
    {
        return QueryException::UNKNOWN_ERROR;
    }


    /**
     * Get the error message text of the last error.
     *
     * @return string
     */
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