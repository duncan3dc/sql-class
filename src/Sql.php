<?php

namespace duncan3dc\SqlClass;

use duncan3dc\Helpers\Helper;
use duncan3dc\SqlClass\Exceptions\QueryException;

/**
 * Main class that manages connections to SQL servers
 */
class Sql
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
     * @var duncan3dc\SqlClass\Engine\AbstractSql The instance of the engine class handling the abstraction
     */
    protected $engine;

    /**
     * @var boolean Flag to indicate whether we are connected to the server yet
     */
    protected $connected;

    /**
     * @var array The options this object was created with
     */
    protected $options;

    /**
     * @var string The type of database we're connected to
     */
    public $mode;

    /**
     * @var resource The connection to the server
     */
    public $server;

    /**
     * @var array The characters used to alias field names
     */
    public $quoteChars;

    /**
     * @var array The sqlite databases that have been attached
     */
    public $attached;

    /**
     * @var array The tables that have been defined and which database they are in
     */
    public $tables;

    /**
     * @var boolean A flag to indicate whether nulls should be used or not
     */
    public $allowNulls;

    /**
     * @var array The options to pass when creating an Cache instance object
     */
    public $cacheOptions;

    /**
     * @var boolean When true the next query we run should be done using cache
     */
    protected $cacheNext;

    /**
     * @var boolean A flag to indicate whether we are currently in transaction mode or not
     */
    protected $transaction;

    /**
     * @var boolean Whether we should log errors to disk or not
     */
    public $log;

    /**
     * @var string The directory to log errors to
     */
    public $logDir;

    /**
     * @var boolean Whether the class should output queries or not
     */
    public $output;

    /**
     * @var boolean Whether the output should be html or plain text
     */
    public $htmlMode;

    /**
     * @var string The query we are currently attempting to run
     */
    protected $query;

    /**
     * @var array The params for the query we are currently attempting to run
     */
    protected $params;

    /**
     * @var string The emulated prepared query we are currently attempting to run
     */
    protected $preparedQuery;

    /**
     * @var array The server definitions that have been registered
     */
    protected static $servers = [];

    /**
     * @var array The instances of the class that have previously been created
     */
    protected static $instances = [];


    public static function addServer($server, array $options)
    {
        if (!$server) {
            throw new \Exception("No name specified for the server to add");
        }

        if (array_key_exists($server, static::$servers)) {
            throw new \Exception("This server (" . $server . ") has already been defined");
        }

        static::$servers[$server] = $options;
    }


    public static function getInstance($server = null)
    {
        # If no server was specified then default to the first one defined
        if (!$server) {
            if (count(static::$servers) < 1) {
                throw new \Exception("No SQL servers have been defined, use " . static::class . "::addServer() before attempting to get an instance.");
            }
            $server = array_keys(static::$servers)[0];
        }

        if (!array_key_exists($server, static::$instances)) {
            static::$instances[$server] = static::getNewInstance($server);
        }

        return static::$instances[$server];
    }


    public static function getNewInstance($server)
    {
        if (!array_key_exists($server, static::$servers)) {
            throw new \Exception("Unknown SQL Server (" . $server . ")");
        }

        $options = static::$servers[$server];
        $construct = [
            "mode"          =>  null,
            "hostname"      =>  null,
            "username"      =>  null,
            "password"      =>  null,
            "database"      =>  null,
            "charset"       =>  null,
            "timezone"      =>  null,
            "definitions"   =>  null,
        ];
        foreach ($construct as $key => $null) {
            if (array_key_exists($key, $options)) {
                $construct[$key] = $options[$key];
            } else {
                unset($construct[$key]);
            }
        }

        $sql = new static($construct);

        $properties = [
            "allowNulls",
            "cacheOptions",
            "log",
            "logDir",
            "output",
            "htmlMode",
        ];
        foreach ($properties as $property) {
            if (array_key_exists($property, $options)) {
                $sql->$property = $options[$property];
            }
        }

        return $sql;
    }


    public function __construct(array $options = null)
    {
        $options = Helper::getOptions($options, [
            "mode"          =>  "mysql",
            "hostname"      =>  "",
            "username"      =>  "",
            "password"      =>  "",
            "database"      =>  false,
            "charset"       =>  "utf8",
            "timezone"      =>  false,
            "definitions"   =>  [],
        ]);

        $this->options = $options;

        $this->mode = $options["mode"];

        $this->quoteChars = [
            "mysql"     =>  "`",
            "postgres"  =>  '"',
            "redshift"  =>  '"',
            "odbc"      =>  '"',
            "sqlite"    =>  "`",
            "mssql"     =>  ["[", "]"],
        ];

        if (!array_key_exists($this->mode, $this->quoteChars)) {
            throw new \Exception("Unsupported mode (" . $this->mode . ")");
        }

        $this->output = false;
        $this->htmlMode = false;

        # Don't allow nulls by default
        $this->allowNulls = false;

        # Don't log by default
        $this->log = false;
        $this->logDir = "/tmp/sql-class-logs";

        $this->attached = [];

        $this->tables = [];

        if ($options["definitions"]) {
            $this->definitions($options["definitions"]);
        }

        $this->cacheOptions = [];
        $this->cacheNext = false;

        $class = __NAMESPACE__ . "\\Engine\\" . ucfirst($this->mode) . "\\Sql";
        $this->engine = new $class;
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

        if (!$this->server = $this->engine->connect($this->options)) {
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
        if ($this->mode != "sqlite") {
            throw new \Exception("You can only attach databases when in sqlite mode");
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

        # If this table's database depends on the mode
        if (is_array($database)) {
            if (array_key_exists($this->mode, $database)) {
                $database = $database[$this->mode];
            } else {
                $database = $database["default"];
            }
        }

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

            if ($this->mode == "mssql") {
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

        if ($this->output) {
            if ($this->htmlMode) {
                echo "<pre>";
            }

            echo $preparedQuery;

            if ($this->htmlMode) {
                echo "<hr>";
            } else {
                echo "\n";
            }
        }

        if (!$result = $this->engine->query($query, $params, $preparedQuery)) {
            $this->error();
        }

        return new Result($result, $this->mode);
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
        $checked = [];

        $chars = $this->quoteChars[$this->mode];
        if (is_array($chars)) {
            $newFrom = $chars[0];
            $newTo = $chars[1];
        } else {
            $newFrom = $chars;
            $newTo = $chars;
        }

        foreach ($this->quoteChars as $mode => $chars) {
            if ($mode == $this->mode) {
                continue;
            }

            if (is_array($chars)) {
                $oldFrom = $chars[0];
                $oldTo = $chars[1];
            } else {
                $oldFrom = $chars;
                $oldTo = $chars;
            }

            if ($oldFrom == $newFrom && $oldTo == $newTo) {
                continue;
            }

            # Create part of the regex that will represent the quoted field we are trying to find
            $match = preg_quote($oldFrom) . "([^" . preg_quote($oldTo) . "]*)" . preg_quote($oldTo);

            # If we've already checked this regex then don't check it again
            if (in_array($match, $checked, true)) {
                continue;
            }
            $checked[] = $match;

            $this->modifyQuery($query, function($part) use($match, $newFrom, $newTo) {
                return preg_replace("/" . $match . "/", $newFrom . "$1" . $newTo, $part);
            });
        }
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

        return new Cache($options);
    }


    protected function error()
    {
        # If logging is turned on then log the error details to the log directory
        if ($this->log) {
            $this->logError();
        }

        throw new QueryException($this->getErrorMessage(), $this->getErrorCode());
    }


    protected function logError()
    {
        if (!$this->log) {
            return;
        }

        # Ensure the log directory exists
        if (!is_dir($this->logDir)) {
            if (!mkdir($this->logDir, 0775, true)) {
                return;
            }
        }

        $logFile = date("Y-m-d_H-i-s") . ".log";

        if (!$file = fopen($this->logDir . "/" . $logFile, "a")) {
            return;
        }

        fwrite($file, "Error Message: " . $this->getErrorMessage() . "\n");
        fwrite($file, "Error Code: " . $this->getErrorCode() . "\n");

        fwrite($file, "SQL ERROR\n");
        if ($this->query) {
            fwrite($file, "Query: " . $this->query . "\n");
        }
        if ($this->params) {
            fwrite($file, "Params: " . print_r($this->params, true) . "\n");
        }
        if ($this->preparedQuery) {
            fwrite($file, "Prepared Query: " . $this->preparedQuery . "\n");
        }
        fwrite($file, "\n");

        fwrite($file, print_r(debug_backtrace(), true));
        fwrite($file, "\n\n");

        fwrite($file, print_r($this, true));
        fwrite($file, "\n\n");

        fwrite($file, "-----------------------------------------------------------------------------\n");
        fwrite($file, "-----------------------------------------------------------------------------\n");
        fwrite($file, "\n\n");

        fclose($file);

        return $logFile;
    }


    public function getErrorCode()
    {
        return $this->engine->getErrorCode();
    }


    public function getErrorMessage()
    {
        return $this->engine->getErrorMessage();
    }


    public function update($table, array $set, $where)
    {
        $tableName = $this->getTableName($table);

        $query = "UPDATE " . $tableName . " SET ";

        $params = [];
        foreach ($set as $key => $val) {
            $query .= $this->quoteField($key) . "=?,";
            $params[] = $val;
        }

        $query = substr($query, 0, -1) . " ";

        if ($where !== self::NO_WHERE_CLAUSE) {
            $query .= "WHERE " . $this->where($where, $params);
        }

        $result = $this->query($query, $params);

        return $result;
    }


    public function insert($table, array $params, $extra = null)
    {
        $tableName = $this->getTableName($table);

        $newParams = [];
        $fields = "";
        $values = "";
        foreach ($params as $key => $val) {
            if ($fields) {
                $fields .= ",";
                $values .= ",";
            }

            $fields .= $this->quoteField($key);
            $values .= "?";
            $newParams[] = $val;
        }

        if ($extra === self::INSERT_REPLACE) {
            $query = "REPLACE ";
        } elseif ($extra === self::INSERT_IGNORE) {
            $query = "INSERT IGNORE ";
        } else {
            $query = "INSERT ";
        }
        $query .= "INTO " . $tableName . " (" . $fields . ") VALUES (" . $values . ")";

        $result = $this->query($query, $newParams);

        return $result;
    }


    /**
     * Allow multiple rows to be inserted much more efficiently.
     *
     * @param string $table The table to insert the records into
     * @param int $limit The maximum number of rows to insert at a time
     *
     * @return BulkInsert
     */
    public function delayedInsert($table, $limit = 10000)
    {
        return new BulkInsert($this, $table, $limit);
    }


    public function bulkInsert($table, array $params, $extra = null)
    {
        # Ensure we have a connection to run this query on
        $this->connect();

        $table = $this->getTableName($table);

        if ($output = $this->output) {
            $this->output = false;
            echo "BULK INSERT INTO " . $table . " (" . count($params) . " rows)...\n";
        }

        $result = $this->engine->bulkInsert($table, $params, $extra);

        if ($output) {
            $this->output = true;
        }

        if (!$result) {
            $this->error();
        }

        return $result;
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

        $params = Helper::toArray($params);

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
        $tableName = $this->getTableName($table);
        $params = null;

        /**
         * If this is a complete empty of the table then the TRUNCATE TABLE statement is a lot faster than issuing a DELETE statement
         * Not all engines support this though, so we have to check which mode we are in
         * Also this statement is not transaction safe, so if we are currently in a transaction then we do not issue the TRUNCATE statement
         */
        if ($where === self::NO_WHERE_CLAUSE && !$this->transaction && !in_array($this->mode, ["odbc", "sqlite"], true)) {
            $query = "TRUNCATE TABLE " . $tableName;
        } else {
            $query = "DELETE FROM " . $tableName . " ";

            if ($where !== self::NO_WHERE_CLAUSE) {
                $query .= "WHERE " . $this->where($where, $params);
            }
        }

        $result = $this->query($query, $params);

        return $result;
    }


    /**
     * Grab the first row from a table using the standard select statement
     * This is a convience method for a fieldSelect() where all fields are required
     */
    public function select($table, $where, $orderBy = null)
    {
        return $this->fieldSelect($table, "*", $where, $orderBy);
    }


    /**
     * Cached version of select()
     */
    public function selectC($table, $where, $orderBy = null)
    {
        $this->cacheNext = true;

        return $this->select($table, $where, $orderBy);
    }


    /**
     * Grab specific fields from the first row from a table using the standard select statement
     */
    public function fieldSelect($table, $fields, $where, $orderBy = null)
    {
        $table = $this->getTableName($table);

        $query = "SELECT ";

        if ($this->mode == "mssql") {
            $query .= "TOP 1 ";
        }

        $query .= $this->selectFields($fields);

        $query .= " FROM " . $table . " ";

        $params = null;
        if ($where !== self::NO_WHERE_CLAUSE) {
            $query .= "WHERE " . $this->where($where, $params);
        }

        if ($orderBy) {
            $query .= $this->orderBy($orderBy) . " ";
        }

        switch ($this->mode) {

            case "mysql":
            case "postgres":
            case "redshift":
            case "sqlite":
                $query .= "LIMIT 1";
                break;

            case "odbc":
                $query .= "FETCH FIRST 1 ROW ONLY";
                break;
        }

        return $this->query($query, $params)->fetch();
    }


    /*
     * Cached version of fieldSelect()
     */
    public function fieldSelectC($table, $fields, $where, $orderBy = null)
    {

        $this->cacheNext = true;

        return $this->fieldSelect($table, $fields, $where, $orderBy);
    }


    /**
     * Create a standard select statement and return the result
     * This is a convience method for a fieldSelectAll() where all fields are required
     */
    public function selectAll($table, $where, $orderBy = null)
    {
        return $this->fieldSelectAll($table, "*", $where, $orderBy);
    }


    /*
     * Cached version of selectAll()
     */
    public function selectAllC($table, $where, $orderBy = null)
    {
        $this->cacheNext = true;

        return $this->selectAll($table, $where, $orderBy);
    }


    /**
     * Create a standard select statement and return the result
     */
    public function fieldSelectAll($table, $fields, $where, $orderBy = null)
    {
        $table = $this->getTableName($table);

        $query = "SELECT ";

        $query .= $this->selectFields($fields);

        $query .= " FROM " . $table . " ";

        $params = null;
        if ($where !== self::NO_WHERE_CLAUSE) {
            $query .= "WHERE " . $this->where($where, $params);
        }

        if ($orderBy) {
            $query .= $this->orderBy($orderBy) . " ";
        }

        return $this->query($query, $params);
    }


    /*
     * Cached version of fieldSelectAll()
     */
    public function fieldSelectAllC($table, $fields, $where, $orderBy = null)
    {
        $this->cacheNext = true;

        return $this->fieldSelectAll($table, $fields, $where, $orderBy);
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
        return (bool) $this->fieldSelect($table, "1", $where);
    }


    /**
     * Insert a new record into a table, unless it already exists in which case update it
     */
    public function insertOrUpdate($table, array $set, array $where)
    {
        if ($this->select($table, $where)) {
            $result = $this->update($table, $set, $where);
        } else {
            $params = array_merge($where, $set);
            $result = $this->insert($table, $params);
        }

        return $result;
    }


    /**
     * Synonym for insertOrUpdate()
     */
    public function updateOrInsert($table, array $set, array $where)
    {
        return $this->insertOrUpdate($table, $set, $where);
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
     * Close the sql connection
     */
    public function disconnect()
    {
        if (!$this->connected || !$this->server) {
            return false;
        }

        return $this->engine->disconnect();
    }
}
