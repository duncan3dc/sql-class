<?php

namespace duncan3dc\SqlClass\Engine;

use duncan3dc\SqlClass\Result;

interface ServerInterface
{

    /**
     * Check if this server supports the TRUNCATE TABLE statement.
     *
     * @return bool
     */
    public function canTruncateTables();


    /**
     * Get the quote characters that this engine uses for quoting identifiers.
     *
     * @return string|string[] Can either be a single quote character that it used, or an array of 2 elements (1 for the start and 1 for the end character)
     */
    public function getQuoteChars();


    /**
     * Connect to the database using the supplied credentials.
     *
     * @param string $hostname The ip address or hostname of the database server
     * @param string $username The user to authenticate with
     * @param string $password The password to authenticate with
     *
     * @return static
     */
    public function connect();


    /**
     * Run a query.
     *
     * @param string $query The query to run
     * @param array $params The parameters to substitute in the query string
     * @param string $preparedQuery A simulated prepared query (if the server doens't support prepared statements)
     *
     * @return Result|boolean SELECT statements will return a Result instance, while INSERT/DELETE/UPDATE statements will return a boolean
     */
    public function query($query, array $params, $preparedQuery);


    /**
     * Convert any supported function for this database mode.
     *
     * eg, the sqlite class would replace SUBSTRING() with SUBSTR()
     *
     * @param string $query The query to manipulate
     *
     * @return string The modified query
     */
    public function changeQuerySyntax($query);


    /**
     * Quote the supplied table with the relevant characters used by the database engine.
     *
     * @param string $table The table name
     *
     * @return string The quoted table name
     */
    public function quoteTable($table);


    /**
     * Quote the supplied string with the relevant characters used by the database engine.
     *
     * @param string $table The string to quote
     *
     * @return string The quoted string
     */
    public function quoteValue($string);


    /**
     * Get the error code of the last error.
     *
     * @return int
     */
    public function getErrorCode();


    /**
     * Get the error message text of the last error.
     *
     * @return string
     */
    public function getErrorMessage();

    public function bulkInsert($table, array $params, $extra = null);

    public function getId(Result $result);

    public function startTransaction();

    public function endTransaction();

    public function commit();

    public function rollback();

    public function lockTables(array $tables);

    public function unlockTables();

    public function getDatabases();

    public function getTables($database);

    public function getViews($database);

    public function disconnect();
}
