<?php

class SqlClass extends SqlClassCommon {

	const   NO_WHERE_CLAUSE = 101;  # Allow queries to be created without a where cluase
	const   USE_PHP_TIMEZONE = 102; # Set the database timezone to be the same as the php one
	const   INSERT_REPLACE = 103;   # Mysql extension to replace any existing records with a unique key match
	const   INSERT_INSERT = 104;    # Mysql extension to ignore any existing records with a unique key match

	public  $mode;			# The type of database we're connected to
	public  $server;                # The connection to the server

	public  $quoteChars;		# The characters used to alias field names

	public  $tables;		# An array of tables defined

	public  $allowNulls;		# A flag to indicate whether nulls should be useds or not

	public  $log;			# The directory to log errors to

	public  $output;                # Whether the class should output queries or not
	public  $htmlMode;              # Whether the output should be html or plain text

	private $query;
	private $params;
	private $preparedQuery;


	public function __construct($options=false) {

		$options = $this->getOptions($options,array(
			"mode"        =>  "mysql",
			"hostname"    =>  "",
			"username"    =>  "",
			"password"    =>  "",
			"database"    =>  false,
			"charset"     =>  "utf8",
			"timezone"    =>  false,
			"definitions" =>  array(),
		));

		$this->mode = $options["mode"];
		if(!in_array($this->mode,array("mysql"))) {
			throw new Exception("Unsupported mode (" . $this->mode . ")");
		}

		$this->quoteChars = array(
			"mysql"		=>	"`",
			"postgres"	=>	'"',
			"mssql"		=>	array("[","]"),
		);

		$this->output = false;
		$this->htmlMode = false;

		# Don't allow nulls by default
		$this->allowNulls = false;

		# Don't log by default
		$this->log = false;
		$this->logDir = "/tmp/sql-class-logs";

		switch($this->mode) {

			case "mysql":
				$this->server = new mysqli($options["hostname"],$options["username"],$options["password"]);
				if($options["charset"]) {
					$this->server->set_charset($options["charset"]);
				}
				if($timezone = $options["timezone"]) {
					if($timezone == self::USE_PHP_TIMEZONE) {
						$timezone = ini_get("date.timezone");
					}
					$this->query("SET time_zone='" . $timezone . "'");
				}
			break;

			case "postgres":
				$connect = "host=" . $options["server"] . " ";
				$connect .= "user=" . $options["username"] . " ";
				$connect .= "password=" . $options["password"] . " ";
				$connect .= "dbname= " . $options["database"] . " ";
				$this->server = pg_connect($connect,PGSQL_CONNECT_FORCE_NEW);
			break;

			case "mssql":
				$this->server = mssql_connect($options["server"],$options["username"],$options["password"]);
			break;

		}

		if(!$this->server) {
			$this->error();
		}

		$this->tables = array();

		if($options["definitions"]) {
			$this->definitions($options["definitions"]);
		}

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
	 * Get the database that should be used for this table
	 */
	public function getTableDatabase($name) {

		if(!$database = $this->tables[$name]) {
			return false;
		}

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

		$this->query = $query;
		$this->params = false;
		$this->preparedQuery = false;

		if(is_array($params)) {
			$this->params = $params;
		}

		$this->query_quoteChars($query);
		$this->query_functions($query);
		$this->query_limit($query);
		$this->query_paramArrays($query,$params);

		$preparedQuery = $this->query_prepare($query,$params);
		$this->preparedQuery = $preparedQuery;
		$this->query_tableNames($query);

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
				}
			break;

			case "postgres":
				$tmpQuery = $query;
				$query = "";

				$i = 1;
				while($pos = strpos($tmpQuery,"?")) {
					$query .= substr($tmpQuery,0,$pos) . "\$" . $i++;
					$tmpQuery = substr($tmpQuery,$pos + 1);
				}
				$query .= $tmpQuery;

				$params = $this->toArray($params);
				if(!$result = pg_query_params($this->server,$query,$params)) {
					$this->error();
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
				if($part[0] != "'") {

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
				$query = preg_replace("/\bISNULL\(/","IFNULL(",$query);
			break;

			case "postgres":
				$query = preg_replace("/\bI[FS]NULL\(/","COALESCE(",$query);
			break;

			case "mssql":
				$query = preg_replace("/\bIFNULL\(/","ISNULL(",$query);
			break;

		}

		switch($this->mode) {

			case "mysql":
			case "postgres":
			case "mssql":
				$query = preg_replace("/\bSUBSTR\(/","SUBSTRING(",$query);
			break;

		}

		switch($this->mode) {

			case "postgres":
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
				$query = preg_replace("/\bFETCH\s+FIRST\s+([0-9]+)\s+ROW(S?)\s+ONLY\b/i","\nLIMIT $1\n",$query);
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
							$value = pg_escape_literal($this->server,$val);
						break;

						case "mssql":
							$value = str_replace("'","''",$val);
						break;

						default:
							$value = $val;
						break;

					}

					# Postgres does it's own quoting
					if($this->mode != "postgres") {
						$value = "'" . $value . "'";
					}

				break;
			}

			$preparedQuery .= $value;

		}

		$preparedQuery .= $tmpQuery;

		return $preparedQuery;

	}


	public function error() {

		# If logging is turned on then log the error details to the log directory
		if($this->log) {
			$this->logError();
		}

		throw new Exception($this->getError());

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
				$errorMsg = pg_last_error($this->server);
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

		return $result;

	}

	public function bulkInsert($table,$params,$extra=false) {

		switch($this->mode) {

			case "mysql":

				$fields = "";
				$first = reset($params);
				foreach($first as $key => $val) {
					if($fields) {
						$fields .= ",";
					}
					$fields .= $this->quoteField($key);
				}

				$newParams = array();
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
						$values .= "?";
						$newParams[] = $val;
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

		}

		if(!$id) {
			throw new Exception("Failed to retrieve the last inserted row id");
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
				$valid = array(
					"<"	=>	"<",
					"<="	=>	"<=",
					"=<"	=>	"<=",
					">"	=>	">",
					">="	=>	">=",
					"=>"	=>	">=",
					"="	=>	"=",
					"<>"	=>	"<>",
					"!="	=>	"<>",
				);

				$first = reset($val);
				$second = next($val);

				# If the array is only one element (or no elements) then just use it as a regular value
				if(count($val) < 2) {
					$query .= "=? ";
					$params[] = $first;

				# If the array is only two elements long and the first element is a valid comparison operator then use it as such
				} elseif(count($val) == 2 && in_array($first,$valid)) {
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
		} else {
			# Otherwise assume it is a string of fields to select and add them to the query
			if(strlen($fields) > 0) {
				$select = $fields;

			}

		}

		return $select;

	}


	public function delete($table,$where) {

		$tableName = $this->getTableName($table);

		/**
		 * If this is a complete empty of the table then the TRUNCATE TABLE statement is a lot faster than issuing a DELETE statement
		 */
		if($where == self::NO_WHERE_CLAUSE) {
			$query = "TRUNCATE TABLE " . $tableName;

		} else {
			$query = "DELETE FROM " . $tableName . "
				WHERE " . $this->where($where,$params);

		}

		$result = $this->query($query,$params);

		return $result;

	}


	/**
	 * Fetch the next row from the result set
	 */
	public function _fetch(&$result) {

		# If the result resource is invalid then don't bother trying to fetch
		if(!$result) {
			return false;
		}

		switch($this->mode) {

			case "mysql":
				$row = $result->fetch_assoc();
			break;

			case "postgres":
				$row = pg_fetch_assoc($result);
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

		switch($this->mode) {

			case "mysql":
				$this->seek($result,$row);
				$data = $this->fetch($result,true);
				$value = $data[$col];
			break;

			case "postgres":
				$value = pg_fetch_result($result,$row,$col);
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
	 * Grab the first row from a table using the standard select statement
	 * This is a convience method for a fieldSelect() where all fields are required
	 */
	public function select($table,$where,$orderBy=false) {

		return $this->fieldSelect($table,"*",$where,$orderBy);

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

		if($where != self::NO_WHERE_CLAUSE) {
			$query .= "WHERE " . $this->where($where,$params);
		}

		if($orderBy) {
			$query .= $this->orderBy($orderBy) . " ";
 		}

		switch($this->mode) {
			case "mysql":
			case "postgres":
				$query .= "LIMIT 1";
			break;
		}

		$result = $this->query($query,$params);

		return $this->fetch($result);

	}


	/**
	 * Create a standard select statement and return the result
	 * This is a convience method for a fieldSelectAll() where all fields are required
	 */
	public function selectAll($table,$where,$orderBy=false) {

		return $this->fieldSelectAll($table,"*",$where,$orderBy);

	}


	/**
	 * Create a standard select statement and return the result
	 */
	public function fieldSelectAll($table,$fields,$where,$orderBy=false) {

		$table = $this->getTableName($table);

		$query = "SELECT ";

		$query .= $this->selectFields($fields);

		$query .= " FROM " . $table . " ";

		if($where != self::NO_WHERE_CLAUSE) {
			$query .= "WHERE " . $this->where($where,$params);
		}

		if($orderBy) {
			$query .= $this->orderBy($orderBy) . " ";
		}

		return $this->query($query,$params);

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

		switch($this->mode) {

			case "mysql":
				$result->data_seek($row);
			break;

			case "postgres":
				pg_result_seek($result,$row);
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

		$table = trim($table);

		# There is a standard function for quoting postgres table names
		if($this->mode == "postgres") {
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
	 * Close the sql connection
	 */
	public function disconnect() {

		if(!$this->server) {
			return false;
		}

		switch($this->mode) {

			case "mysql":
				$result = $this->server->close();
			break;

			case "postgres":
				$result = pg_close($this->server);
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

		$this->disconnect();

	}


}
