<?php

namespace duncan3dc\SqlClass;

use Psr\Log\LoggerInterface;

interface SqlInterface
{
    /**
     * Allow queries to be created without a where cluase
     */
    const NO_WHERE_CLAUSE = 101;

    /**
     * Set the database timezone to be the same as the php one
     */
    const USE_PHP_TIMEZONE = 102;

    /**
     * Mysql extension to replace any existing records with a unique key match
     */
    const INSERT_REPLACE = 103;

    /**
     * Mysql extension to ignore any existing records with a unique key match
     */
    const INSERT_IGNORE = 104;

    /**
     * Return rows as an enumerated array (using column numbers)
     */
    const FETCH_ROW = 108;

    /**
     * Return rows as an associative array (using field names)
     */
    const FETCH_ASSOC = 109;

    /**
     * Return a generator of the first 1 or 2 columns
     */
    const FETCH_GENERATOR = 110;

    /**
     * Return the raw row from the database without performing cleanup
     */
    const FETCH_RAW = 111;


    /**
     * Get the engine instance.
     *
     * @return ServerInterface
     */
    public function getEngine();


    /**
     * Set the logger to use.
     *
     * @param LoggerInterface $logger Which logger to use
     *
     * @return static
     */
    public function setLogger(LoggerInterface $logger);


    /**
     * Get the logger in use.
     *
     * @return LoggerInterface
     */
    public function getLogger();


    /**
     * If we have not already connected then connect to the database now.
     *
     * @return static
     */
    public function connect();


    /*
     * Define which database each table is located in.
     */
    public function definitions($data);


    /**
     * Attach another sqlite database to the current connection.
     */
    public function attachDatabase($filename, $database = null);


    /**
     * Execute an sql query.
     */
    public function query($query, array $params = null);


    /**
     * Convenience method to create a cached query instance.
     */
    public function cache($query, array $params = null, $timeout = null);


    public function error();


    public function getErrorCode();


    public function getErrorMessage();


    public function table($table);


    public function update($table, array $set, $where);


    public function insert($table, array $params, $extra = null);


    public function bulkInsert($table, array $params, $extra = null);


    public function getId(ResultInterface $result);


    /**
     * Convert an array of parameters into a valid where clause
     */
    public function where($where, &$params);


    /**
     * Convert an array/string of fields into a valid select clause
     */
    public function selectFields($fields);


    public function delete($table, $where);


    /**
     * Grab the first row from a table using the standard select statement
     * This is a convience method for a fieldSelect() where all fields are required
     */
    public function select($table, $where, $orderBy = null);


    /**
     * Cached version of select()
     */
    public function selectC($table, $where, $orderBy = null);


    /**
     * Grab specific fields from the first row from a table using the standard select statement
     */
    public function fieldSelect($table, $fields, $where, $orderBy = null);


    /*
     * Cached version of fieldSelect()
     */
    public function fieldSelectC($table, $fields, $where, $orderBy = null);


    /**
     * Create a standard select statement and return the result
     * This is a convience method for a fieldSelectAll() where all fields are required
     */
    public function selectAll($table, $where, $orderBy = null);


    /*
     * Cached version of selectAll()
     */
    public function selectAllC($table, $where, $orderBy = null);


    /**
     * Create a standard select statement and return the result
     */
    public function fieldSelectAll($table, $fields, $where, $orderBy = null);


    /*
     * Cached version of fieldSelectAll()
     */
    public function fieldSelectAllC($table, $fields, $where, $orderBy = null);


    /**
     * Check if a record exists without fetching any data from it.
     *
     * @param string $table The table name to fetch from
     * @param array|int $where The where clause to use, or the NO_WHERE_CLAUSE constant
     *
     * @return boolean Whether a matching row exists in the table or not
     */
    public function exists($table, $where);


    /**
     * Insert a new record into a table, unless it already exists in which case update it
     */
    public function insertOrUpdate($table, array $set, array $where);


    /**
     * Synonym for insertOrUpdate()
     */
    public function updateOrInsert($table, array $set, array $where);


    /**
     * Create an order by clause from a string of fields or an array of fields
     */
    public function orderBy($fields);


    /**
     * This method allows easy appending of search criteria to queries
     * It takes existing query/params to be edited as the first 2 parameters
     * The third parameter is the string that is being searched for
     * The fourth parameter is an array of fields that should be searched for in the sql
     */
    public function search(&$query, array &$params, $search, array $fields);


    /**
     * Start a transaction by turning autocommit off.
     */
    public function startTransaction();


    /**
     * Check if the object is currently in transaction mode.
     *
     * @return bool
     */
    public function isTransaction();


    /**
     * End a transaction by either committing changes made, or reverting them.
     */
    public function endTransaction($commit);


    /**
     * Commit queries without ending the transaction.
     */
    public function commit();


    /**
     * Rollback queries without ending the transaction.
     */
    public function rollback();


    /**
     * Lock some tables for exlusive write access
     * But allow read access to other processes
     */
    public function lockTables($tables);


    /**
     * Unlock all tables previously locked.
     */
    public function unlockTables();


    public function getDatabases();


    public function getTables($database);


    public function getViews($database);


    /**
     * Close the sql connection.
     *
     * @return void
     */
    public function disconnect();
}
