<?php

namespace SqlClass;

class Sql extends Common {

    const   NO_WHERE_CLAUSE  = 101;     # Allow queries to be created without a where cluase
    const   USE_PHP_TIMEZONE = 102;     # Set the database timezone to be the same as the php one
    const   INSERT_REPLACE   = 103;     # Mysql extension to replace any existing records with a unique key match
    const   INSERT_IGNORE    = 104;     # Mysql extension to ignore any existing records with a unique key match
    const   TRIGGER_INSERT   = 105;     # A trigger to be run after a successful insert
    const   TRIGGER_UPDATE   = 106;     # A trigger to be run after a successful update
    const   TRIGGER_DELETE   = 107;     # A trigger to be run after a successful delete

    public  $mode;                      # The type of database we're connected to
    public  $server;                    # The connection to the server

    public  $quoteChars;                # The characters used to alias field names

    public  $attached;                  # An array of the sqlite databases that have been attached

    public  $tables;                    # An array of tables defined

    public  $allowNulls;                # A flag to indicate whether nulls should be useds or not

    public  $cacheOptions;              # An array of options to pass when initiating an Cache instance
    public  $cacheNext;                 # Internal flag to indicate the next query we run should be done using cache

    public  $triggers;                  # An array of triggers that have been registered

    public  $transaction;               # A flag to indicate whether we are currently in transaction mode or not

    public  $log;                       # The directory to log errors to

    public  $output;                    # Whether the class should output queries or not
    public  $htmlMode;                  # Whether the output should be html or plain text

    private $query;
    private $params;
    private $preparedQuery;


    public function __construct($options=false) {

        $options = $this->getOptions($options,array(
            "mode"          =>  "mysql",
            "hostname"      =>  "",
            "username"      =>  "",
            "password"      =>  "",
            "database"      =>  false,
            "charset"       =>  "utf8",
            "timezone"      =>  false,
            "definitions"   =>  array(),
        ));

        $this->mode = $options["mode"];

        $this->quoteChars = array(
            "mysql"     =>  "`",
            "postgres"  =>  '"',
            "redshift"  =>  '"',
            "odbc"      =>  '"',
            "sqlite"    =>  "`",
            "mssql"     =>  array("[","]"),
        );

        if(!array_key_exists($this->mode,$this->quoteChars)) {
            throw new \Exception("Unsupported mode (" . $this->mode . ")");
        }

        $this->output = false;
        $this->htmlMode = false;

        # Create the empty triggers array, with each acceptable type
        $this->triggers = array(
            self::TRIGGER_INSERT => array(),
            self::TRIGGER_UPDATE => array(),
            self::TRIGGER_DELETE => array(),
        );

        # Don't allow nulls by default
        $this->allowNulls = false;

        # Don't log by default
        $this->log = false;
        $this->logDir = "/tmp/sql-class-logs";

        switch($this->mode) {

            case "mysql":
                if(!$this->server = new \mysqli($options["hostname"],$options["username"],$options["password"])) {
                    $this->error();
                }
                if($options["charset"]) {
                    $this->server->set_charset($options["charset"]);
                }
                if($timezone = $options["timezone"]) {
                    if($timezone == self::USE_PHP_TIMEZONE) {
                        $timezone = ini_get("date.timezone");
                    }
                    $this->query("SET time_zone='" . $timezone . "'");
                }
                if($database = $options["database"]) {
                    if(!$this->server->select_db($database)) {
                        $this->error();
                    }
                }
            break;

            case "postgres":
            case "redshift":
                $connect = "host=" . $options["server"] . " ";
                $connect .= "user=" . $options["username"] . " ";
                $connect .= "password=" . $options["password"] . " ";
                $connect .= "dbname= " . $options["database"] . " ";
                $this->server = pg_connect($connect,PGSQL_CONNECT_FORCE_NEW);
            break;

            case "odbc":
                $this->server = odbc_connect($options["server"], $options["username"], $options["password"]);
            break;

            case "sqlite":
                $this->server = new \Sqlite3($options["database"]);
            break;

            case "mssql":
                $this->server = mssql_connect($options["server"],$options["username"],$options["password"]);
            break;

        }

        if(!$this->server) {
            $this->error();
        }

        $this->attached = array();

        $this->tables = array();

        if($options["definitions"]) {
            $this->definitions($options["definitions"]);
        }

        $this->cacheOptions = array();
        $this->cacheNext = false;

    }


    /*
     * Define which database each table is located in
     */
    public function definitions($data) {

        # Either specified as an array of tables
        if(is_array($data)) {
            $tables = $data;

        # Or as an includable script with a $tables array defined in it
        } else {
            require($data);

        }

        $this->tables = array_merge($this->tables,$tables);

    }


    /**
     * Attach another sqlite database to the current connection
     */
    public function attachDatabase($filename,$database=false) {

        if($this->mode != "sqlite") {
            throw new \Exception("You can only attach databases when in sqlite mode");
        }

        if(!$database) {
            $database = pathinfo($filename,PATHINFO_FILENAME);
        }

        $query = "ATTACH DATABASE '" . $filename . "' AS " . $this->quoteTable($database);
        $result = $this->query($query);

        if(!$result) {
            $this->error();
        }

        $this->attached[$database] = $filename;

        return $result;

    }


    /**
     * Get the database that should be used for this table
     */
    public function getTableDatabase($name) {

        if(!array_key_exists($name,$this->tables)) {
            return false;
        }

        $database = $this->tables[$name];

        # If this table's database depends on the mode
        if(is_array($database)) {
            if(array_key_exists($this->mode,$database)) {
                $database = $database[$this->mode];
            } else {
                $database = $database["default"];
            }
        }

        return $database;

    }


    /**
     * Get the full table name (including database)
     * If the database isn't passed then look it up first
     */
    public function getTableName($name,$database=false) {

        if(!$database) {
            $database = $this->getTableDatabase($name);
        }

        if($database) {
            switch($this->mode) {
                case "mssql":
                    $table = $this->quoteField($database) . ".dbo." . $this->quoteField($name);
                break;
                default:
                    $table = $this->quoteField($database) . "." . $this->quoteField($name);
                break;
            }

        } else {
            $table = $name;

        }

        return $table;

    }


    /**
     * Execute an sql query
     */
    public function query($query,$params=false) {

        # If the next query should be cached then run the cache function instead
        if($this->cacheNext) {
            $this->cacheNext = false;
            return $this->cache($query,$params);
        }

        $this->query = $query;
        $this->params = false;
        $this->preparedQuery = false;

        if(is_array($params)) {
            $this->params = $params;
        }

        $this->query_quoteChars($query);
        $this->query_functions($query);
        $this->query_limit($query);
        $this->query_tableNames($query);
        $this->query_paramArrays($query,$params);

        $preparedQuery = $this->query_prepare($query,$params);
        $this->preparedQuery = $preparedQuery;

        if($this->output) {
            if($this->htmlMode) {
                echo "<pre>";
            }

            echo $preparedQuery;

            if($this->htmlMode) {
                echo "<hr>";
            } else {
                echo "\n";
            }
        }

        switch($this->mode) {

            case "mysql":
                if(!$result = $this->server->query($preparedQuery)) {
                    $this->error();
                $noParams = false;
                if($this->mode == "redshift" && count($params) > 32767) {
                    $noParams = true;
                }

                }
            break;

            case "postgres":
            case "redshift":
                $tmpQuery = $query;
                $query = "";

                $i = 1;
                while($pos = strpos($tmpQuery,"?")) {
                    if($noParams) {
                        $query .= substr($tmpQuery,0,$pos) . "'" . pg_escape_string(array_shift($params)) . "'";
                    } else {
                        $query .= substr($tmpQuery,0,$pos) . "\$" . $i++;
                    }
                    $tmpQuery = substr($tmpQuery,$pos + 1);
                }
                $query .= $tmpQuery;

                $params = $this->toArray($params);
                if(!$result = pg_query_params($this->server,$query,$params)) {
                    $this->error();
                }
            break;

            case "odbc":
                if(!$result = odbc_prepare($this->server,$query)) {
                    $this->error();
                }
                $params = $this->toArray($params);
                if(!odbc_execute($result,$params)) {
                    $this->error();
                }
            break;

            case "sqlite":

                if(!is_array($params)) {
                    if(!$result = $this->server->query($preparedQuery)) {
                        $this->error();
                    }

                } else {

                    $newQuery = "";
                    foreach($params as $key => $val) {
                        $pos = strpos($query,"?");
                        $newQuery .= substr($query,0,$pos);
                        $query = substr($query,$pos + 1);

                        $newQuery .= ":var" . $key;
                    }
                    $newQuery .= $query;

                    if(!$result = $this->server->prepare($newQuery)) {
                        $this->error();
                    }

                    foreach($params as $key => $val) {
                        switch(gettype($val)) {
                            case "boolean":
                            case "integer":
                                $type = SQLITE3_INTEGER;
                            break;
                            case "double":
                                $type = SQLITE3_FLOAT;
                            break;
                            case "NULL":
                                if($this->allowNulls) {
                                    $type = SQLITE3_NULL;
                                } else {
                                    $type = SQLITE3_TEXT;
                                    $val = "";
                                }
                            break;
                            default:
                                $type = SQLITE3_TEXT;
                            break;
                        }

                        $result->bindValue(":var" . $key, $val, $type);
                    }

                    if(!$result = $result->execute()) {
                        $this->error();
                    }

                }
            break;

            case "mssql":
                if(!$result = mssql_query($preparedQuery,$this->server)) {
                    $this->error();
                }
            break;

        }

        if(!$result) {
            $this->error();
        }

        return $result;

    }


    /**
     * Replace any quote characters used to the appropriate type for the current mode
     * This function attempts to ignore any instances that are surrounded by single quotes, as these should not be converted
     */
     public function query_quoteChars(&$query) {

        $checked = array();

        $chars = $this->quoteChars[$this->mode];
        if(is_array($chars)) {
            $newFrom = $chars[0];
            $newTo = $chars[1];
        } else {
            $newFrom = $chars;
            $newTo = $chars;
        }

        foreach($this->quoteChars as $mode => $chars) {
            if($mode == $this->mode) {
                continue;
            }

            if(is_array($chars)) {
                $oldFrom = $chars[0];
                $oldTo = $chars[1];
            } else {
                $oldFrom = $chars;
                $oldTo = $chars;
            }

            if($oldFrom == $newFrom && $oldTo == $newTo) {
                continue;
            }

            # Create part of the regex that will represent the quoted field we are trying to find
            $match = preg_quote($oldFrom) . "([^" . preg_quote($oldTo) . "]*)" . preg_quote($oldTo);

            # If we've already checked this regex then don't check it again
            if(in_array($match,$checked)) {
                continue;
            }
            $checked[] = $match;

            /**
             * Break up the query by single quoted strings
             * This is because we don't want to modify the contents of these strings
             */
            $parts = preg_split("/('[^']*')/",$query,null,PREG_SPLIT_DELIM_CAPTURE);
            $query = "";
            foreach($parts as $part) {

                # If this part of the query isn't a string, then perform the replace on it
                if(substr($part,0,1) != "'") {

                    # If the replace was successful then override this part of the query with the new part
                    if($newPart = preg_replace("/" . $match . "/","$newFrom$1$newTo",$part)) {
                        $part = $newPart;
                    }
                }

                # Tag this part of the query onto the new query we are constructing
                $query .= $part;

            }

        }

        return true;

    }


    /**
     * Replace any non-standard functions with the appropriate function for the current mode
     */
    public function query_functions(&$query) {

        switch($this->mode) {

            case "mysql":
            case "odbc":
            case "sqlite":
                $query = preg_replace("/\bISNULL\(/","IFNULL(",$query);
            break;

            case "postgres":
            case "redshift":
                $query = preg_replace("/\bI[FS]NULL\(/","COALESCE(",$query);
            break;

            case "mssql":
                $query = preg_replace("/\bIFNULL\(/","ISNULL(",$query);
            break;

        }

        switch($this->mode) {

            case "mysql":
            case "postgres":
            case "redshift":
            case "odbc":
            case "mssql":
                $query = preg_replace("/\bSUBSTR\(/","SUBSTRING(",$query);
            break;

            case "sqlite":
                $query = preg_replace("/\bSUBSTRING\(/","SUBSTR(",$query);
            break;

        }

        switch($this->mode) {

            case "postgres":
            case "redshift":
                $query = preg_replace("/\FROM_UNIXTIME\(([^,\)]+),(\s*)([^\)]+)\)/","TO_CHAR(ABSTIME($1),$3)",$query);
            break;

        }

        return true;

    }


    /**
     * Convert any limit usage
     * Doesn't work with the mssql variety
     */
    public function query_limit(&$query) {

        switch($this->mode) {

            case "mysql":
            case "postgres":
            case "redshift":
            case "sqlite":
                $query = preg_replace("/\bFETCH\s+FIRST\s+([0-9]+)\s+ROW(S?)\s+ONLY\b/i","\nLIMIT $1\n",$query);
            break;

            case "odbc":
                $query = preg_replace("/\bLIMIT\s+([0-9]+)\b/i","\nFETCH FIRST $1 ROWS ONLY\n",$query);
            break;

        }

        return true;

    }


    /**
     * Convert table references to full database/table names
     * This allows tables to be surrounded in braces, without specifying the database
     */
    public function query_tableNames(&$query) {

        while(preg_match("/{([^}]*)}/",$query,$matches)) {
            $table = $this->getTableName($matches[1]);
            $query = str_replace($matches[0],$table,$query);
        }

        return true;

    }


    /**
     * If any of the parameters are arrays, then convert the single marker from the query to handle them
     */
    public function query_paramArrays(&$query,&$params) {

        if(!is_array($params)) {
            return false;
        }

        $tmpQuery = $query;
        $newQuery = "";
        $newParams = array();

        foreach($params as $val) {

            $pos = strpos($tmpQuery,"?");

            $newQuery .= substr($tmpQuery,0,$pos);
            $tmpQuery = substr($tmpQuery,$pos+1);

            if(is_array($val)) {
                if(count($val) > 1) {
                    $markers = array();
                    foreach($val as $v) {
                        $markers[] = "?";
                        $newParams[] = $v;
                    }
                    $newQuery .= "(" . implode(",",$markers) . ")";

                } else {
                    $newQuery = preg_replace("/\s*\bNOT\s+IN\s*$/i","<>",$newQuery);
                    $newQuery = preg_replace("/\s*\bIN\s*$/i","=",$newQuery);
                    $newQuery .= "?";
                    $newParams[] = reset($val);

                }

            } else {
                $newQuery .= "?";
                $newParams[] = $val;

            }

        }

        $newQuery .= $tmpQuery;

        $query = $newQuery;
        $params = $newParams;

        return true;

    }


    public function query_prepare($query,&$params) {

        if(!is_array($params)) {
            return $query;
        }

        $preparedQuery = "";
        $tmpQuery = $query;

        foreach($params as &$val) {

            $pos = strpos($tmpQuery,"?");
            $preparedQuery .= substr($tmpQuery,0,$pos);
            $tmpQuery = substr($tmpQuery,$pos + 1);

            switch(gettype($val)) {
                case "boolean":
                    $value = round($val);
                break;
                case "integer":
                case "double":
                    $value = $val;
                break;

                case "NULL":
                    # If nulls are allowed then set the value to be null and break out of the switch
                    if($this->allowNulls) {
                        $value = "NULL";
                        break;

                    # If nulls aren't allowed then convert this value in the params array
                    } else {
                        $val = "";

                    }

                    # If nulls aren't allowed then interpret them as an empty string

                default:
                    switch($this->mode) {

                        case "mysql":
                            $value = $this->server->real_escape_string($val);
                        break;

                        case "postgres":
                        case "redshift":
                            $value = pg_escape_literal($this->server,$val);
                        break;

                        case "odbc":
                        case "mssql":
                            $value = str_replace("'","''",$val);
                        break;

                        case "sqlite":
                            $value = $this->server->escapeString($val);
                        break;

                        default:
                            $value = $val;
                        break;

                    }

                    # Postgres does it's own quoting
                    if(!in_array($this->mode,array("postgres","redshift"))) {
                        $value = "'" . $value . "'";
                    }

                break;
            }

            $preparedQuery .= $value;

        }
        unset($val);

        $preparedQuery .= $tmpQuery;

        return $preparedQuery;

    }


    /**
     * Convienience method to create a cached query instance
     */
    public function cache($query,$params=false,$timeout=false) {

        $options = array_merge($this->cacheOptions,array(
            "sql"     =>  $this,
            "query"   =>  $query,
            "params"  =>  $params,
        ));

        if($timeout) {
            $options["timeout"] = $timeout;
        }

        return new Cache($options);

    }


    public function error() {

        # If logging is turned on then log the error details to the log directory
        if($this->log) {
            $this->logError();
        }

        throw new \Exception($this->getError());

    }


    public function logError() {

        if(!$this->log) {
            return false;
        }

        # Ensure the log directory exists
        if(!is_dir($this->logDir)) {
            if(!mkdir($this->logDir,0775,true)) {
                return false;
            }
        }

        $logFile = date("Y-m-d_H-i-s") . ".log";

        if(!$file = fopen($this->logDir . "/" . $logFile,"a")) {
            return false;
        }

        fwrite($file,"Error: " . $this->getError() . "\n");

        fwrite($file,"SQL ERROR\n");
        if($this->query) {
            fwrite($file,"Query: " . $this->query . "\n");
        }
        if($this->params) {
            fwrite($file,"Params: " . print_r($this->params,1) . "\n");
        }
        if($this->preparedQuery) {
            fwrite($file,"Prepared Query: " . $this->preparedQuery . "\n");
        }
        fwrite($file,"\n");

        fwrite($file,print_r(debug_backtrace(),1));
        fwrite($file,"\n\n");

        fwrite($file,print_r($this,1));
        fwrite($file,"\n\n");

        fwrite($file,"-----------------------------------------------------------------------------\n");
        fwrite($file,"-----------------------------------------------------------------------------\n");
        fwrite($file,"\n\n");

        fclose($file);

        return $logFile;

    }


    public function getError() {

        $errorMsg = "";

        switch($this->mode) {

            case "mysql":
                if($this->server->connect_error) {
                    $errorMsg = $this->server->connect_error . " (" . $this->server->connect_errno . ")";
                } else {
                    $errorMsg = $this->server->error . " (" . $this->server->errno . ")";
                }
            break;

            case "postgres":
            case "redshift":
                $errorMsg = pg_last_error($this->server);
            break;

            case "odbc":
                $errorMsg = odbc_errormsg($this->server);
            break;

            case "sqlite":
                $errorMsg = $this->server->lastErrorMsg() . " (" . $this->server->lastErrorCode() . ")";
            break;

            case "mssql":
                $errorMsg = mssql_get_last_message();
            break;

        }

        return $errorMsg;

    }


    public function update($table,$set,$where) {

        $tableName = $this->getTableName($table);

        $query = "UPDATE " . $tableName . " SET ";

        $params = array();
        foreach($set as $key => $val) {
            $query .= $this->quoteField($key) . "=?,";
            $params[] = $val;
        }

        $query = substr($query,0,-1) . " ";

        if($where != self::NO_WHERE_CLAUSE) {
            $query .= "WHERE " . $this->where($where,$params);
        }

        $result = $this->query($query,$params);

        $this->callTriggers(self::TRIGGER_UPDATE,$table,$set,$where);

        return $result;

    }


    public function insert($table,$params) {

        $tableName = $this->getTableName($table);

        $newParams = array();
        foreach($params as $key => $val) {
            if($fields) {
                $fields .= ",";
                $values .= ",";
            }

            $fields .= $this->quoteField($key);
            $values .= "?";
            $newParams[] = $val;
        }

        $query = "INSERT INTO " . $tableName . " (" . $fields . ") VALUES (" . $values . ")";

        $result = $this->query($query,$newParams);

        $this->callTriggers(self::TRIGGER_INSERT,$table,$params);

        return $result;

    }

    public function bulkInsert($table,$params,$extra=false) {

        if($output = $this->output) {
            $this->output = false;
            echo "BULK INSERT INTO " . $table . " (" . count($params) . " rows)...\n";
        }

        switch($this->mode) {

            case "mysql":
            case "redshift":
            case "odbc":

                $fields = "";
                $first = reset($params);
                foreach($first as $key => $val) {
                    if($fields) {
                        $fields .= ",";
                    }
                    $fields .= $this->quoteField($key);
                }

                $newParams = array();
                $noParams = false;
                if($this->mode == "redshift" && (count($params) * count($first)) > 32767) {
                    $noParams = true;
                }
                $values = "";

                foreach($params as $row) {
                    if($values) {
                        $values .= ",";
                    }
                    $values .= "(";
                    $first = true;

                    foreach($row as $key => $val) {
                        if($first) {
                            $first = false;
                        } else {
                            $values .= ",";
                        }
                        if($noParams) {
                            $values .= "'" . pg_escape_string($val) . "'";
                        } else {
                            $values .= "?";
                            $newParams[] = $val;
                        }
                    }
                    $values .= ")";
                }

                $tableName = $this->getTableName($table);
                if($extra == self::INSERT_REPLACE) {
                    $query = "REPLACE ";
                } elseif($extra == self::INSERT_IGNORE) {
                    $query = "INSERT IGNORE ";
                } else {
                    $query = "INSERT ";
                }
                $query .= "INTO " . $tableName . " (" . $fields . ") VALUES " . $values;

                $result = $this->query($query,$newParams);

            break;

            case "postgres":
                $fields = "";
                $first = reset($params);
                foreach($first as $key => $val) {
                    if($fields) {
                        $fields .= ",";
                    }
                    $fields .= $this->quoteField($key);
                }

                $tableName = $this->getTableName($table);
                $this->query("COPY " . $tableName . " (" . $fields . ") FROM STDIN");

                foreach($params as $row) {
                    if(!pg_put_line($this->server,implode("\t",$row) . "\n")) {
                        $this->error();
                    }
                }

                if(pg_put_line($this->server, "\\.\n")) {
                    $this->error();
                }

                $result = pg_end_copy($this->server);

            break;

            default:
                $result = true;
                foreach($params as $newParams) {
                    if(!$this->insert($table,$newParams)) {
                        $result = false;
                        break;
                    }
                }
            break;

        }

        if(!$result) {
            $this->error();
        }

        if($output) {
            $this->output = true;
        }

        return $result;

    }


    public function getId($result) {

        if(!$result) {
            return false;
        }

        $id = false;

        switch($this->mode) {

            case "mysql":
                $id = $this->server->insert_id;
            break;

            case "postgres":
                $id = pg_last_oid($result);
            break;

            case "sqlite":
                $query = "SELECT last_insert_rowid() `id`";
                $result = $this->query($query);
                $row = $this->fetch($result);
                $id = $row["id"];
            break;

        }

        if(!$id) {
            throw new \Exception("Failed to retrieve the last inserted row id");
        }

        return $id;

    }


    /**
     * Convert an array of parameters into a valid where clause
     */
    public function where($where,&$params) {

        $params = $this->toArray($params);

        $query = "";

        $andFlag = false;

        foreach($where as $key => $val) {

            # Add the and flag if this isn't the first field
            if($andFlag) {
                $query .= "AND ";
            } else {
                $andFlag = true;
            }

            # Add the field name to the query
            $query .= $this->quoteField($key);

            # If the value is not an array then use a standard comparison
            if(!is_array($val)) {
                $query .= "=? ";
                $params[] = $val;

            # Special processing for arrays
            } else {
                $first = reset($val);
                $second = next($val);

                # If the array is only one element (or no elements) then just use it as a regular value
                if(count($val) < 2) {
                    $query .= "=? ";
                    $params[] = $first;

                # If the array is only two elements long and the first element is a valid comparison operator then use it as such
                } elseif(count($val) == 2 && in_array($first,array("<","<=",">",">=","=","<>"))) {
                    $query .= $first . "? ";
                    $params[] = $second;

                # Otherwise treat the array as a set of values for an IN()
                } else {
                    $markers = array();
                    foreach($val as $v) {
                        $markers[] = "?";
                        $params[] = $v;
                    }
                    $query .= " IN(" . implode(",",$markers) . ") ";

                }

            }

        }

        return $query;

    }


    /**
     * Convert an array/string of fields into a valid select clause
     */
    public function selectFields($fields) {

        # By default just select an empty string
        $select = "''";

        # If an array of fields have been passed
        if(is_array($fields)) {

            # If we have some fields, then add them to the query, ensuring they are quoted appropriately
            if(count($fields) > 0) {
                $select = "";

                foreach($fields as $field) {
                    if($select) {
                        $select .= ", ";
                    }
                    $select .= $this->quoteField($field);
                }

            }

        # if the fields isn't an array
        } elseif(!is_bool($fields)) {
            # Otherwise assume it is a string of fields to select and add them to the query
            if(strlen($fields) > 0) {
                $select = $fields;

            }

        }

        return $select;

    }


    public function delete($table,$where) {

        $tableName = $this->getTableName($table);
        $params = false;

        /**
         * If this is a complete empty of the table then the TRUNCATE TABLE statement is a lot faster than issuing a DELETE statement
         * Not all engines support this though, so we have to check which mode we are in
         * Also this statement is not transaction safe, so if we are currently in a transaction then we do not issue the TRUNCATE statement
         */
        if($where == self::NO_WHERE_CLAUSE && !$this->transaction && $this->mode != "odbc") {
            $query = "TRUNCATE TABLE " . $tableName;

        } else {
            $query = "DELETE FROM " . $tableName . " ";

            if($where != self::NO_WHERE_CLAUSE) {
                $query .= "WHERE " . $this->where($where,$params);
            }

        }

        $result = $this->query($query,$params);

        $this->callTriggers(self::TRIGGER_DELETE,$table,$where);

        return $result;

    }


    /**
     * Fetch the next row from the result set
     */
    public function _fetch(&$result) {

        # If this is an object created by the cache class then run the fetch method directly on it
        if($result instanceof Cache) {
            return $result->fetch($indexed);
        }

        # If the result resource is invalid then don't bother trying to fetch
        if(!$result) {
            return false;
        }

        switch($this->mode) {

            case "mysql":
                $row = $result->fetch_assoc();
            break;

            case "postgres":
            case "redshift":
                $row = pg_fetch_assoc($result);
            break;

            case "odbc":
                $row = odbc_fetch_array($result);
            break;

            case "sqlite":
                $row = $result->fetchArray(SQLITE3_ASSOC);
            break;

            case "mssql":
                $row = mssql_fetch_assoc($result);
            break;

        }

        # If the fetch fails then there are no rows left to retrieve
        if(!$row) {
            return false;
        }

        return $row;

    }


    /**
     * Fetch the next row from the result set and clean it up
     */
    public function fetch(&$result,$indexed=false) {

        # If the fetch fails then there are no rows left to retrieve
        if(!$data = $this->_fetch($result)) {
            return false;
        }

        $row = array();

        foreach($data as $key => $val) {

            $val = rtrim($val);

            # If the data should be returned as an enumerated array then ignore the key
            if($indexed) {
                $row[] = $val;

            } else {
                $key = strtolower($key);
                $row[$key] = $val;

            }

        }

        return $row;

    }


    /**
     * Fetch an indiviual value from the result set
     */
    public function result(&$result,$row,$col) {

        if(!$result) {
            return false;
        }

        # If this is an object created by the cache class then run the result method directly on it
        if($result instanceof Cache) {
            return $result->result($row,$col);
        }

        switch($this->mode) {

            case "mysql":
            case "sqlite":
                $this->seek($result,$row);
                $data = $this->fetch($result,true);
                $value = $data[$col];
            break;

            case "postgres":
            case "redshift":
                $value = pg_fetch_result($result,$row,$col);
            break;

            case "odbc":
                odbc_fetch_row($result,($row + 1));
                $value = odbc_result($result,($col + 1));
            break;

            case "mssql":
                $value = mssql_result($result,$row,$col);
            break;

        }

        $value = rtrim($value);

        return $value;

    }


    /**
     * Execute the query and fetch the first row from the result set
     * This is just a shorter way of doing a query() and then a fetch()
     */
    public function queryFetch($query,$params=false,$indexed=false) {

        $result = $this->query($query,$params);

        return $this->fetch($result,$indexed);

    }


    /**
     * Cached version of queryFetch()
     */
    public function queryFetchC($query,$params=false,$indexed=false) {

        $this->cacheNext = true;

        return $this->queryFetch($query,$params,$indexed);

    }


    /**
     * Execute the query and get a specific value from the result set
     * This is just a shorter way of doing a query() and then a result()
     */
    public function queryResult($query,$params,$row,$col) {

        $result = $this->query($query,$params);

        return $this->result($result,$row,$col);

    }


    /**
     * Cached version of queryResult()
     */
    public function queryResultC($query,$params,$row,$col) {

        $this->cacheNext = true;

        return $this->queryResult($query,$params,$row,$col);

    }


    /**
     * Grab the first row from a table using the standard select statement
     * This is a convience method for a fieldSelect() where all fields are required
     */
    public function select($table,$where,$orderBy=false) {

        return $this->fieldSelect($table,"*",$where,$orderBy);

    }


    /**
     * Cached version of select()
     */
    public function selectC($table,$where,$orderBy=false) {

        $this->cacheNext = true;

        return $this->select($table,$where,$orderBy);

    }


    /**
     * Grab specific fields from the first row from a table using the standard select statement
     */
    public function fieldSelect($table,$fields,$where,$orderBy=false) {

        $table = $this->getTableName($table);

        $query = "SELECT ";

        if($this->mode == "mssql") {
            $query .= "TOP 1 ";
        }

        $query .= $this->selectFields($fields);

        $query .= " FROM " . $table . " ";

        $params = false;
        if($where != self::NO_WHERE_CLAUSE) {
            $query .= "WHERE " . $this->where($where,$params);
        }

        if($orderBy) {
            $query .= $this->orderBy($orderBy) . " ";
        }

        switch($this->mode) {

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

        $result = $this->query($query,$params);

        return $this->fetch($result);

    }


    /*
     * Cached version of fieldSelect()
     */
    public function fieldSelectC($table,$fields,$where,$orderBy=false) {

        $this->cacheNext = true;

        return $this->fieldSelect($table,$fields,$where,$orderBy);

    }


    /**
     * Create a standard select statement and return the result
     * This is a convience method for a fieldSelectAll() where all fields are required
     */
    public function selectAll($table,$where,$orderBy=false) {

        return $this->fieldSelectAll($table,"*",$where,$orderBy);

    }


    /*
     * Cached version of selectAll()
     */
    public function selectAllC($table,$where,$orderBy=false) {

        $this->cacheNext = true;

        return $this->selectAll($table,$where,$orderBy);

    }


    /**
     * Create a standard select statement and return the result
     */
    public function fieldSelectAll($table,$fields,$where,$orderBy=false) {

        $table = $this->getTableName($table);

        $query = "SELECT ";

        $query .= $this->selectFields($fields);

        $query .= " FROM " . $table . " ";

        $params = false;
        if($where != self::NO_WHERE_CLAUSE) {
            $query .= "WHERE " . $this->where($where,$params);
        }

        if($orderBy) {
            $query .= $this->orderBy($orderBy) . " ";
        }

        return $this->query($query,$params);

    }


    /*
     * Cached version of fieldSelectAll()
     */
    public function fieldSelectAllC($table,$fields,$where,$orderBy=false) {

        $this->cacheNext = true;

        return $this->fieldSelectAll($table,$fields,$where,$orderBy);

    }


    /**
     * Insert a new record into a table, unless it already exists in which case update it
     */
    public function insertOrUpdate($table,$set,$where) {

        if($this->select($table,$where)) {
            $result = $this->update($table,$set,$where);

        } else {
            $params = array_merge($where,$set);
            $result = $this->insert($table,$params);

        }

        return $result;

    }


    /**
     * Synonym for insertOrUpdate()
     */
    public function updateOrInsert($table,$set,$where) {

        return $this->insertOrUpdate($table,$set,$where);

    }


    /**
     * Create an order by clause from a string of fields or an array of fields
     */
    public function orderBy($fields) {

        if(!is_array($fields)) {
            $fields = explode(",",$fields);
        }

        $orderBy = "";

        foreach($fields as $field) {
            if(!$field = trim($field)) {
                continue;
            }
            if(!$orderBy) {
                $orderBy = "ORDER BY ";
            } else {
                $orderBy .= ", ";
            }

            if(strpos($field," ")) {
                $orderBy .= $field;
            } else {
                $orderBy .= $this->quoteField($field);
            }
        }

        return $orderBy;

    }


    /**
     * Seek to a specific record of the result set
     */
    public function seek(&$result,$row) {

        # If this is an object created by the cache class then run the seek method directly on it
        if($result instanceof Cache) {
            return $result->seek($row);
        }

        switch($this->mode) {

            case "mysql":
                $result->data_seek($row);
            break;

            case "postgres":
            case "redshift":
                pg_result_seek($result,$row);
            break;

            case "odbc":
                # This actually does a seek and fetch, so although the rows are numbered 1 higher than other databases, this will still work
                odbc_fetch_row($result,$row);
            break;

            case "sqlite":
                $result->reset();
                for($i = 0; $i < $row; $i++) {
                    $this->fetch($result);
                }
            break;

            case "mssql":
                mssql_data_seek($result,$row);
            break;

        }

    }


    /**
     * Quote a field with the appropriate characters for this mode
     */
    public function quoteField($field) {

        # The odbc sql only uses it's quote strings for renaming fields, not for quoting table/field names
        if($this->mode == "odbc") {
            return $field;
        }

        $field = trim($field);

        $chars = $this->quoteChars[$this->mode];

        if(is_array($chars)) {
            $from = $chars[0];
            $to = $chars[1];

        } else {
            $from = $chars;
            $to = $chars;

        }

        $quoted = $from . $field . $to;

        return $quoted;

    }


    /**
     * Quote a table with the appropriate characters for this mode
     */
    public function quoteTable($table) {

        # The odbc sql only uses it's quote strings for renaming fields, not for quoting table/field names
        if($this->mode == "odbc") {
            return $table;
        }

        $table = trim($table);

        # There is a standard function for quoting postgres table names
        if(in_array($this->mode,array("postgres","redshift"))) {
            return pg_escape_identifier($this->server,$table);
        }

        $chars = $this->quoteChars[$this->mode];

        if(is_array($chars)) {
            $from = $chars[0];
            $to = $chars[1];

        } else {
            $from = $chars;
            $to = $chars;

        }

        $quoted = $from . $table . $to;

        return $quoted;

    }


    /**
     * This method allows easy appending of search criteria to queries
     * It takes existing query/params to be edited as the first 2 parameters
     * The third parameter is the string that is being searched for
     * The fourth parameter is an array of fields that should be searched for in the sql
     */
    public function search(&$query,&$params,$search,$fields) {

        $query .= "( ";

        $search = str_replace('"','',$search);

        $words = explode(" ",$search);

        foreach($words as $key => $word) {

            if($key) {
                $query .= "AND ";
            }

            $query .= "( ";
                foreach($fields as $key => $field) {
                    if($key) {
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
    public function startTransaction() {

        switch($this->mode) {

            case "mysql":
                $result = $this->server->autocommit(false);
            break;

            case "postgres":
                $result = $this->query("SET AUTOCOMMIT = OFF");
            break;

            case "redshift":
                $result = $this->query("START TRANSACTION");
            break;

            case "odbc":
                $result = odbc_autocommit($this->server,false);
            break;

            default:
                throw new \Exception("startTransaction() not supported in this mode (" . $this->mode . ")");
            break;

        }

        if(!$result) {
            $this->error();
        }

        $this->transaction = true;

        return true;

    }


    /**
     * End a transaction by either committing changes made, or reverting them
     */
    public function endTransaction($commit) {

        if($commit) {
            $result = $this->commit();
        } else {
            $result = $this->rollback();
        }

        switch($this->mode) {

            case "mysql":
                if(!$this->server->autocommit(true)) {
                    $result = false;
                }
            break;

            case "postgres":
                $result = $this->query("SET AUTOCOMMIT = ON");
            break;

            case "redshift":
                # Do nothing, and use the result from the commit/rollback
            break;

            case "odbc":
                if(!odbc_autocommit($this->server,true)) {
                    $result = false;
                }
            break;

            default:
                throw new \Exception("endTransaction() not supported in this mode (" . $this->mode . ")");
            break;

        }

        if(!$result) {
            $this->error();
        }

        $this->transaction = false;

        return true;

    }


    /**
     * Commit queries without ending the transaction
     */
    public function commit() {

        switch($this->mode) {

            case "mysql":
                $result = $this->server->commit();
            break;

            case "postgres":
            case "redshift":
                $result = $this->query("COMMIT");
            break;

            case "odbc":
                $result = odbc_commit($this->server);
            break;

            default:
                throw new \Exception("commit() not supported in this mode (" . $this->mode . ")");
            break;

        }

        if(!$result) {
            $this->error();
        }

        return true;

    }


    /**
     * Rollback queries without ending the transaction
     */
    public function rollback() {

        switch($this->mode) {

            case "mysql":
                $result = $this->server->rollback();
            break;

            case "postgres":
            case "redshift":
                $result = $this->query("ROLLBACK");
            break;

            case "odbc":
                $result = odbc_rollback($this->server);
            break;

            default:
                throw new \Exception("rollback() not supported in this mode (" . $this->mode . ")");
            break;

        }

        if(!$result) {
            $this->error();
        }

        return true;

    }


    /**
     * Lock some tables for exlusive write access
     * But allow read access to other processes
     */
    public function lockTables($tables) {

        /**
         * Unlock any previously locked tables
         * This is done to provide consistency across different modes, as mysql only allows one single lock over multiple tables
         * Also the odbc only allows all locks to be released, not individual tables. So it makes sense to force the batching of lock/unlock operations
         */
        $this->unlockTables();

        $tables = $this->toArray($tables);

        if($this->mode == "odbc") {
            foreach($tables as $table) {
                $table = $this->getTableName($table);
                $query = "LOCK TABLE " . $table . " IN EXCLUSIVE MODE ALLOW READ";
                $this->query($query);
            }

            # If none of the locks failed then report success
            return true;
        }

        foreach($tables as &$table) {
            $table = $this->getTableName($table);
        }
        unset($table);

        if($this->mode == "mysql") {
            $query = "LOCK TABLES " . implode(",",$tables) . " WRITE";
            return $this->query($query);
        }

        if(in_array($this->mode,array("postgres","redshift"))) {
            $query = "LOCK TABLE " . implode(",",$tables) . " IN EXCLUSIVE MODE";
            return $this->query($query);
        }

        throw new \Exception("lockTables() not supported in this mode (" . $this->mode . ")");

    }


    /**
     * Unlock all tables previously locked
     */
    public function unlockTables() {

        switch($this->mode) {

            case "mysql":
                $query = "UNLOCK TABLES";
            break;

            case "postgres":
            case "redshift":
            case "odbc":
                $query = "COMMIT";
            break;

            default:
                throw new \Exception("unlockTables() not supported in this mode (" . $this->mode . ")");
            break;

        }

        return $this->query($query);

    }


    /**
     * Register a trigger to be called when a query is run using one of the built in methods (update/insert/delete)
     */
    public function addTrigger($type,$table,$trigger) {

        if(!array_key_exists($type,$this->triggers)) {
            throw new \Exception("Invalid trigger type specified");
        }

        $this->triggers[$type][$table][] = $trigger;

    }


    /**
     * Call any triggers that were previously registered using addTrigger()
     */
    public function callTriggers($type,$table,$params1,$params2=false) {

        $triggers = $this->triggers[$type][$table];

        if(!is_array($triggers)) {
            return true;
        }

        foreach($triggers as $trigger) {
            $result = $trigger(array(
                "sql"      =>  $this,
                "type"     =>  $type,
                "table"    =>  $table,
                "params1"  =>  $params1,
                "params2"  =>  $params2,
            ));
            if(!$result) {
                return false;
            }
        }

        return true;

    }


    public function getDatabases() {

        switch($this->mode) {

            case "mysql":
                $query = "SHOW DATABASES";
            break;

            case "mssql":
                $query = "SELECT name FROM master..sysdatabases";
            break;

            default:
                throw new \Exception("getDatabases() not supported in this mode (" . $this->mode . ")");
            break;

        }

        $databases = array();
        $result = $this->query($query);
        while($row = $this->fetch($result,true)) {
            $databases[] = $row[0];
        }

        return $databases;

    }


    public function getTables($database) {

        switch($this->mode) {

            case "mysql":
                $query = "SHOW FULL TABLES IN " . $this->quoteTable($database) . " WHERE table_type='BASE TABLE'";
            break;

            case "mssql":
                $query = "SELECT name FROM " . $this->quoteTable($database) . ".sys.tables";
            break;

            default:
                throw new \Exception("getTables() not supported in this mode (" . $this->mode . ")");
            break;

        }

        $tables = array();
        $result = $this->query($query);
        while($row = $this->fetch($result,true)) {
            $tables[] = $row[0];
        }

        return $tables;

    }


    public function getViews($database) {

        switch($this->mode) {

            case "mysql":
                $query = "SHOW FULL TABLES IN " . $this->quoteTable($database) . " WHERE table_type='VIEW'";
            break;

            case "mssql":
                $query = "SELECT name FROM " . $this->quoteTable($database) . ".sys.views";
            break;

            default:
                throw new \Exception("getViews() not supported in this mode (" . $this->mode . ")");
            break;

        }

        $views = array();
        $result = $this->query($query);
        while($row = $this->fetch($result,true)) {
            $views[] = $row[0];
        }

        return $views;

    }


    /**
     * Close the sql connection
     */
    public function disconnect() {

        if(!$this->server) {
            return false;
        }

        switch($this->mode) {

            case "mysql":
            case "sqlite":
                $result = $this->server->close();
            break;

            case "postgres":
            case "redshift":
                $result = pg_close($this->server);
            break;

            case "odbc":
                odbc_close($this->server);
                $result = true;
            break;

            case "mssql":
                $result = mssql_close($this->server);
            break;

        }

        return $result;

    }


    /**
     * Automatically close the connection on destruction
     */
    public function __destruct() {

        /**
         * Don't automatically close odbc connections, as odbc_connect() re-uses connections with the same credentials
         * So closing here could affect another instance of the sql class
         */
        if($this->mode != "odbc") {
            $this->disconnect();
        }

    }


}
