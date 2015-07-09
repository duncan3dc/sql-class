<?php

namespace duncan3dc\SqlClass;

use duncan3dc\Helpers\Helper;
use duncan3dc\SqlClass\Engine\ResultInterface;
use duncan3dc\SqlClass\Engine\ServerInterface;
use duncan3dc\SqlClass\Exceptions\QueryException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Main class that manages connections to SQL servers.
 *
 * @method Where in(array $values) in($value1, $value2, ...) Helper method for IN() clauses
 */
class Sql implements LoggerAwareInterface
{
    use LoggerAwareTrait;

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
     * @var ServerInterface $engine The instance of the engine class handling the abstraction
     */
    protected $engine;

    /**
     * @var boolean $connected Flag to indicate whether we are connected to the server yet
     */
    protected $connected;

    /**
     * @var resource $server The connection to the server
     */
    public $server;

    /**
     * @var array $attached The sqlite databases that have been attached
     */
    public $attached = [];

    /**
     * @var array $tables The tables that have been defined and which database they are in
     */
    public $tables = [];

    /**
     * @var boolean $allowNulls A flag to indicate whether nulls should be used or not
     */
    public $allowNulls = false;

    /**
     * @var array $cacheOptions The options to pass when creating an CachedResult instance object
     */
    public $cacheOptions = [];

    /**
     * @var boolean $cacheNext When true the next query we run should be done using cache
     */
    protected $cacheNext = false;

    /**
     * @var boolean $transaction A flag to indicate whether we are currently in transaction mode or not
     */
    protected $transaction;

    /**
     * @var string $query The query we are currently attempting to run
     */
    protected $query;

    /**
     * @var array $params The params for the query we are currently attempting to run
     */
    protected $params;

    /**
     * @var string $preparedQuery The emulated prepared query we are currently attempting to run
     */
    protected $preparedQuery;

    /**
     * @var ServerInterface[] $servers The server definitions that have been registered
     */
    protected static $servers = [];

    /**
     * @var array $properties The properties that should be applied to each server when instantiated.
     */
    protected static $properties = [];

    /**
     * @var array Sql[] $instances The instances of the class that have previously been created
     */
    protected static $instances = [];


    public static function addServer($name, ServerInterface $server, array $properties = null)
    {
        if (!$name) {
            throw new \Exception("No name specified for the server to add");
        }

        if (array_key_exists($name, static::$servers)) {
            throw new \Exception("This server ({$name}) has already been defined");
        }

        static::$servers[$name] = $server;
        static::$properties[$name] = $properties ?: [];
    }


    public static function getInstance($name = null)
    {
        # If no server was specified then default to the first one defined
        if (!$name) {
            if (count(static::$names) < 1) {
                throw new \Exception("No SQL servers have been defined, use " . static::class . "::addServer() before attempting to get an instance.");
            }
            $name = array_keys(static::$servers)[0];
        }

        if (!array_key_exists($name, static::$instances)) {
            static::$instances[$name] = static::getNewInstance($name);
        }

        return static::$instances[$name];
    }


    public static function getNewInstance($name)
    {
        if (!array_key_exists($name, static::$servers)) {
            throw new \Exception("Unknown SQL Server: {$name}");
        }

        $server = static::$servers[$name];

        $sql = new static($server);

        $properties = [
            "allowNulls",
            "cacheOptions",
        ];
        foreach ($properties as $property) {
            if (array_key_exists($property, static::$properties[$name])) {
                $sql->$property = static::$properties[$name][$property];
            }
        }

        return $sql;
    }


    public function __construct(ServerInterface $server, LoggerInterface $logger = null)
    {
        $this->engine = $server;
        $this->engine->setSql($this);

        if ($logger === null) {
            $logger = new NullLogger;
        }
        $this->setLogger($logger);
    }


    /**
     * Get the engine instance.
     *
     * @return ServerInterface
     */
    public function getEngine()
    {
        return $this->engine;
    }


    /**
     * Set the logger to use.
     *
     * @param LoggerInterface $logger Which logger to use
     *
     * @return static
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }


    /**
     * Get the logger in use.
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }


    /**
     * If we have not already connected then connect to the database now.
     *
     * @return static
     */
    public function connect()
    {
        if ($this->connected) {
            return $this;
        }

        # Set that we are connected here, because queries can be run as part of the below code, which would cause an infinite loop
        $this->connected = true;

        if (!$this->server = $this->engine->connect()) {
            $this->connected = false;
            $this->error();
        }

        $this->engine->setServer($this->server);

        return $this;
    }


    /*
     * Define which database each table is located in
     */
    public function definitions($data)
    {
        # Either specified as an array of tables
        if (is_array($data)) {
            $tables = $data;

        # Or as an includable script with a $tables array defined in it
        } else {
            require $data;
        }

        $this->tables = array_merge($this->tables, $tables);
    }


    /**
     * Attach another sqlite database to the current connection
     */
    public function attachDatabase($filename, $database = null)
    {
        if (!$this->engine instanceof Engine\Sqlite\Server) {
            throw new \Exception("You can only attach databases when using the Sqlite engine");
        }

        if (!$database) {
            $database = pathinfo($filename, PATHINFO_FILENAME);
        }

        $query = "ATTACH DATABASE '" . $filename . "' AS " . $this->quoteTable($database);
        $result = $this->query($query);

        if (!$result) {
            $this->error();
        }

        $this->attached[$database] = $filename;

        return $result;
    }


    /**
     * Get the database that should be used for this table
     */
    protected function getTableDatabase($table)
    {
        if (!array_key_exists($table, $this->tables)) {
            return false;
        }

        $database = $this->tables[$table];

        return $database;
    }


    /**
     * Get the full table name, including the database.
     *
     * @param string $table The table name
     */
    protected function getTableName($table)
    {
        $database = $this->getTableDatabase($table);

        # If we found a database for this table then include it in the return value
        if ($database) {
            $database = $this->quoteField($database);

            if ($this->engine instanceof Engine\Mssql\Server) {
                $database .= ".dbo";
            }

            return $database . "." . $this->quoteField($table);
        }

        # If we didn't find a database, and this table already looks like it includes
        if (strpos($table, ".") !== false) {
            return $table;
        }


        return $this->quoteField($table);
    }


    /**
     * Execute an sql query
     */
    public function query($query, array $params = null)
    {
        # If the next query should be cached then run the cache function instead
        if ($this->cacheNext) {
            $this->cacheNext = false;
            return $this->cache($query, $params);
        }

        # Ensure we have a connection to run this query on
        $this->connect();

        $this->query = $query;
        $this->params = null;
        $this->preparedQuery = false;

        if (is_array($params)) {
            $this->params = $params;
        }

        $this->quoteChars($query);
        $this->changeQuerySyntax($query);
        $this->tableNames($query);
        $this->namedParams($query, $params);
        $this->paramArrays($query, $params);
        $this->convertNulls($params);

        $preparedQuery = $this->prepareQuery($query, $params);
        $this->preparedQuery = $preparedQuery;

        $this->logger->debug($preparedQuery);

        $result = $this->engine->query($query, $params, $preparedQuery);

        if (!$result instanceof ResultInterface) {
            $this->error();
        }

        return new Result($result);
    }


    /*
     * Allow a query to be modified without affecting quoted strings within it
     */
    protected function modifyQuery(&$query, callable $callback)
    {
        $regex = "/('[^']*')/";
        if (!preg_match($regex, $query)) {
            $query = $callback($query);
            return;
        }

        $parts = preg_split($regex, $query, null, PREG_SPLIT_DELIM_CAPTURE);

        $query = "";

        foreach ($parts as $part) {

            # If this part of the query isn't a string, then perform the replace on it
            if (substr($part, 0, 1) != "'") {
                $part = $callback($part);
            }

            # Append this part of the query onto the new query we are constructing
            $query .= $part;
        }
    }


    /**
     * Replace any quote characters used to the appropriate type for the current mode
     * This function attempts to ignore any instances that are surrounded by single quotes, as these should not be converted
     */
    protected function quoteChars(&$query)
    {
        $quoteChar = "`";

        $chars = $this->engine->getQuoteChars();
        if (is_array($chars)) {
            $start = $chars[0];
            $end = $chars[1];
        } else {
            $start = $chars;
            $end = $chars;
        }

        if ($start === $quoteChar && $end === $quoteChar) {
            return;
        }

        # Create part of the regex that will represent the quoted field we are trying to find
        $match = $quoteChar . "([^" . $quoteChar . "]*)" . $quoteChar;

        $this->modifyQuery($query, function($part) use($match, $start, $end) {
            return preg_replace("/" . $match . "/", $start . "$1" . $end, $part);
        });
    }


    /**
     * Replace any non-standard functions with the appropriate function for the current mode.
     */
    protected function changeQuerySyntax(&$query)
    {
        $query = $this->engine->changeQuerySyntax($query);
    }


    /**
     * Convert table references to full database/table names
     * This allows tables to be surrounded in braces, without specifying the database
     */
    protected function tableNames(&$query)
    {
        $this->modifyQuery($query, function($part) {
            return preg_replace_callback("/{([^}]+)}/", function($match) {
                return $this->getTableName($match[1]);
            }, $part);
        });
    }


    /**
     * If any of the parameters are arrays, then convert the single marker from the query to handle them
     */
    protected function paramArrays(&$query, &$params)
    {
        if (!is_array($params)) {
            return;
        }

        $tmpQuery = $query;
        $newQuery = "";
        $newParams = [];

        foreach ($params as $val) {

            $pos = strpos($tmpQuery, "?");
            if ($pos === false) {
                continue;
            }

            $newQuery .= substr($tmpQuery, 0, $pos);
            $tmpQuery = substr($tmpQuery, $pos + 1);

            if (is_array($val)) {
                if (count($val) > 1) {
                    $markers = [];
                    foreach ($val as $v) {
                        $markers[] = "?";
                        $newParams[] = $v;
                    }
                    $newQuery .= "(" . implode(",", $markers) . ")";

                # If the array is only 1 element long then convert it to an = (or <> for NOT IN)
                } else {
                    $newQuery = preg_replace("/\s*\bNOT\s+IN\s*$/i", "<>", $newQuery);
                    $newQuery = preg_replace("/\s*\bIN\s*$/i", "=", $newQuery);
                    $newQuery .= "?";
                    $newParams[] = reset($val);
                }

            # If this is just a straight value then don't do anything to it
            } else {
                $newQuery .= "?";
                $newParams[] = $val;
            }
        }

        $newQuery .= $tmpQuery;

        $query = $newQuery;
        $params = $newParams;
    }


    /**
     * If the params array uses named keys then convert them to the regular markers
     */
    protected function namedParams(&$query, &$params)
    {
        if (!is_array($params)) {
            return;
        }

        $pattern = "a-zA-Z0-9_";

        if (!preg_match("/\?([" . $pattern . "]+)/", $query)) {
            return;
        }

        $oldParams = $params;
        $params = [];

        reset($oldParams);
        $this->modifyQuery($query, function($part) use(&$params, &$oldParams, $pattern) {
            return preg_replace_callback("/\?([" . $pattern . "]*)([^" . $pattern . "]|$)/", function($match) use(&$params, &$oldParams) {
                if ($key = $match[1]) {
                    $params[] = $oldParams[$key];
                } else {
                    $params[] = current($oldParams);
                    next($oldParams);
                }
                return "?" . $match[2];
            }, $part);
        });
    }


    protected function convertNulls(&$params)
    {
        if ($this->allowNulls) {
            return;
        }

        if (!is_array($params)) {
            return;
        }

        foreach ($params as &$val) {
            if (gettype($val) == "NULL") {
                $val = "";
            }
        }
    }


    protected function prepareQuery($query, $params)
    {
        if (!is_array($params)) {
            return $query;
        }

        reset($params);
        $this->modifyQuery($query, function($part) use(&$params) {
            $newPart = "";
            while ($pos = strpos($part, "?")) {
                $newPart .= substr($part, 0, $pos);
                $part = substr($part, $pos + 1);

                $value = current($params);
                next($params);

                switch (gettype($value)) {

                    case "boolean":
                        $value = (int)$value;
                        break;

                    case "integer":
                    case "double":
                        break;

                    case "NULL":
                        $value = "NULL";
                        break;

                    default:
                        $value = $this->engine->quoteValue($value);
                        break;
                }

                $newPart .= $value;
            }

            return $newPart . $part;
        });

        return $query;
    }


    /**
     * Convienience method to create a cached query instance
     */
    public function cache($query, array $params = null, $timeout = null)
    {
        $options = array_merge($this->cacheOptions, [
            "sql"     =>  $this,
            "query"   =>  $query,
            "params"  =>  $params,
        ]);

        if ($timeout) {
            $options["timeout"] = $timeout;
        }

        return new CachedResult($options);
    }


    public function error()
    {
        $this->logger->error($this->getErrorMessage(), [
            "code"      =>  $this->getErrorCode(),
            "query"     =>  $this->query,
            "params"    =>  $this->params,
            "prepared"  =>  $this->preparedQuery,
            "backtrace" =>  debug_backtrace(),
        ]);

        throw new QueryException($this->getErrorMessage(), $this->getErrorCode());
    }


    public function getErrorCode()
    {
        return $this->engine->getErrorCode();
    }


    public function getErrorMessage()
    {
        return $this->engine->getErrorMessage();
    }


    public function table($table)
    {
        $tableName = $this->getTableName($table);
        return new Table($tableName, $this);
    }


    public function update($table, array $set, $where)
    {
        return $this->table($table)->update($set, $where);
    }


    public function insert($table, array $params, $extra = null)
    {
        return $this->table($table)->insert($params, $extra);
    }


    public function bulkInsert($table, array $params, $extra = null)
    {
        return $this->table($table)->bulkInsert($params, $extra);
    }


    public function getId(Result $result)
    {
        if (!$id = $this->engine->getId($result)) {
            throw new \Exception("Failed to retrieve the last inserted row id");
        }
        return $id;
    }


    /**
     * Convert an array of parameters into a valid where clause
     */
    public function where($where, &$params)
    {
        if (!is_array($where)) {
            throw new \Exception("Invalid where clause specified, must be an array");
        }

        if (!is_array($params)) {
            $params = [];
        }

        $query = "";

        $andFlag = false;

        foreach ($where as $field => $value) {

            # Add the and flag if this isn't the first field
            if ($andFlag) {
                $query .= "AND ";
            } else {
                $andFlag = true;
            }

            # Add the field name to the query
            $query .= $this->quoteField($field);

            # Convert arrays to use the in helper
            if (is_array($value)) {
                $value = static::in($value);
            }

            # Any parameters not using a helper should use the standard equals helper
            if (!is_object($value)) {
                $value = static::equals($value);
            }

            $query .= " " . $value->getClause() . " ";
            foreach ($value->getValues() as $val) {
                $params[] = $val;
            }
        }

        return $query;
    }


    /**
     * Convert an array/string of fields into a valid select clause
     */
    public function selectFields($fields)
    {
        # By default just select an empty string
        $select = "''";

        # If an array of fields have been passed
        if (is_array($fields)) {

            # If we have some fields, then add them to the query, ensuring they are quoted appropriately
            if (count($fields) > 0) {
                $select = "";

                foreach ($fields as $field) {
                    if ($select) {
                        $select .= ", ";
                    }
                    $select .= $this->quoteField($field);
                }
            }

        # if the fields isn't an array
        } elseif (!is_bool($fields)) {
            # Otherwise assume it is a string of fields to select and add them to the query
            if (strlen($fields) > 0) {
                $select = $fields;
            }
        }

        return $select;
    }


    public function delete($table, $where)
    {
        return $this->table($table)->delete($where);
    }

    /**
     * Grab the first row from a table using the standard select statement
     * This is a convience method for a fieldSelect() where all fields are required
     */
    public function select($table, $where, $orderBy = null)
    {
        return $this->table($table)->select($where, $orderBy);
    }


    /**
     * Cached version of select()
     */
    public function selectC($table, $where, $orderBy = null)
    {
        return $this->table($table)->cacheNext()->select($where, $orderBy);
    }


    /**
     * Grab specific fields from the first row from a table using the standard select statement
     */
    public function fieldSelect($table, $fields, $where, $orderBy = null)
    {
        return $this->table($table)->fieldSelect($fields, $where, $orderBy);
    }


    /*
     * Cached version of fieldSelect()
     */
    public function fieldSelectC($table, $fields, $where, $orderBy = null)
    {
        return $this->table($table)->cacheNext()->fieldSelect($fields, $where, $orderBy);
    }


    /**
     * Create a standard select statement and return the result
     * This is a convience method for a fieldSelectAll() where all fields are required
     */
    public function selectAll($table, $where, $orderBy = null)
    {
        return $this->table($table)->selectAll($where, $orderBy);
    }


    /*
     * Cached version of selectAll()
     */
    public function selectAllC($table, $where, $orderBy = null)
    {
        return $this->table($table)->cacheNext()->selectAll($where, $orderBy);
    }


    /**
     * Create a standard select statement and return the result
     */
    public function fieldSelectAll($table, $fields, $where, $orderBy = null)
    {
        return $this->table($table)->fieldSelectAll($fields, $where, $orderBy);
    }


    /*
     * Cached version of fieldSelectAll()
     */
    public function fieldSelectAllC($table, $fields, $where, $orderBy = null)
    {
        return $this->table($table)->cacheNext()->fieldSelectAll($fields, $where, $orderBy);
    }


    /**
     * Check if a record exists without fetching any data from it.
     *
     * @param string $table The table name to fetch from
     * @param array|int $where The where clause to use, or the NO_WHERE_CLAUSE constant
     *
     * @return boolean Whether a matching row exists in the table or not
     */
    public function exists($table, $where)
    {
        return $this->table($table)->exists($where);
    }


    /**
     * Insert a new record into a table, unless it already exists in which case update it
     */
    public function insertOrUpdate($table, array $set, array $where)
    {
        return $this->table($table)->insertOrUpdate($set, $where);
    }


    /**
     * Synonym for insertOrUpdate()
     */
    public function updateOrInsert($table, array $set, array $where)
    {
        return $this->table($table)->insertOrUpdate($set, $where);
    }


    /**
     * Create an order by clause from a string of fields or an array of fields
     */
    public function orderBy($fields)
    {
        if (!is_array($fields)) {
            $fields = explode(",", $fields);
        }

        $orderBy = "";

        foreach ($fields as $field) {
            if (!$field = trim($field)) {
                continue;
            }
            if (!$orderBy) {
                $orderBy = "ORDER BY ";
            } else {
                $orderBy .= ", ";
            }

            if (strpos($field, " ")) {
                $orderBy .= $field;
            } else {
                $orderBy .= $this->quoteField($field);
            }
        }

        return $orderBy;
    }


    /**
     * Quote a field with the appropriate characters for this mode
     */
    protected function quoteField($field)
    {
        $field = trim($field);

        return $this->engine->quoteField($field);
    }


    /**
     * Quote a table with the appropriate characters for this mode
     */
    protected function quoteTable($table)
    {
        $table = trim($table);

        return $this->engine->quoteTable($table);
    }


    /**
     * This method allows easy appending of search criteria to queries
     * It takes existing query/params to be edited as the first 2 parameters
     * The third parameter is the string that is being searched for
     * The fourth parameter is an array of fields that should be searched for in the sql
     */
    public function search(&$query, array &$params, $search, array $fields)
    {
        $query .= "( ";

        $search = str_replace('"', '', $search);

        $words = explode(" ", $search);

        foreach ($words as $key => $word) {

            if ($key) {
                $query .= "AND ";
            }

            $query .= "( ";
                foreach ($fields as $key => $field) {
                    if ($key) {
                        $query .= "OR ";
                    }
                    $query .= "LOWER(" . $field . ") LIKE ? ";
                    $params[] = "%" . strtolower(trim($word)) . "%";
                }
            $query .= ") ";
        }

        $query .= ") ";
    }


    /**
     * Start a transaction by turning autocommit off
     */
    public function startTransaction()
    {
        # Ensure we have a connection to start the transaction on
        $this->connect();

        if (!$result = $this->engine->startTransaction()) {
            $this->error();
        }

        $this->transaction = true;

        return true;
    }


    /**
     * Check if the object is currently in transaction mode.
     *
     * @return bool
     */
    public function isTransaction()
    {
        return $this->transaction;
    }


    /**
     * End a transaction by either committing changes made, or reverting them
     */
    public function endTransaction($commit)
    {
        if ($commit) {
            $result = $this->commit();
        } else {
            $result = $this->rollback();
        }

        if (!$result = $this->engine->endTransaction()) {
            $this->error();
        }

        $this->transaction = false;

        return true;
    }


    /**
     * Commit queries without ending the transaction
     */
    public function commit()
    {
        if (!$result = $this->engine->commit()) {
            $this->error();
        }

        return true;
    }


    /**
     * Rollback queries without ending the transaction
     */
    public function rollback()
    {
        if (!$result = $this->engine->rollback()) {
            $this->error();
        }

        return true;
    }


    /**
     * Lock some tables for exlusive write access
     * But allow read access to other processes
     */
    public function lockTables($tables)
    {
        /**
         * Unlock any previously locked tables
         * This is done to provide consistency across different modes, as mysql only allows one single lock over multiple tables
         * Also the odbc only allows all locks to be released, not individual tables. So it makes sense to force the batching of lock/unlock operations
         */
        $this->unlockTables();

        $tables = Helper::toArray($tables);

        foreach ($tables as &$table) {
            $table = $this->getTableName($table);
        }
        unset($table);

        return $this->engine->lockTables($tables);
    }


    /**
     * Unlock all tables previously locked
     */
    public function unlockTables()
    {
        return $this->engine->unlockTables();
    }


    public function getDatabases()
    {
        return $this->engine->getDatabases();
    }


    public function getTables($database)
    {
        return $this->engine->getTables();
    }


    public function getViews($database)
    {
        return $this->engine->getViews();
    }


    public static function __callStatic($method, $params)
    {
        $className = ucfirst($method);

        if ($className === "GreaterThanOrEqualTo") {
            $className = "NotLessThan";
        } elseif ($className === "LessThanOrEqualTo") {
            $className = "NotGreaterThan";
        } elseif ($className === "EqualTo") {
            $className = "Equals";
        }

        $class = __NAMESPACE__ . "\\Where\\{$className}";
        if (class_exists($class)) {
            return new $class(...$params);
        }
        throw new \Exception("Invalid method: {$method}");
    }

    /**
     * Close the sql connection.
     *
     * @return void
     */
    public function disconnect()
    {
        if (!$this->connected || !$this->server) {
            return;
        }

        $this->engine->disconnect();
    }
}
