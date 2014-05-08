<?php

class SqlClass extends SqlClassCommon {

	public  $server;                # The connection to the server

	public  $output;                # Whether the class should output queries or not
	public  $htmlMode;              # Whether the output should be html or plain text

	private $query;
	private $params;
	private $preparedQuery;


	public function __construct($options=false) {

		$options = $this->getOptions($options,array(
			"hostname"    =>  "",
			"username"    =>  "",
			"password"    =>  "",
			"database"    =>  false,
			"charset"     =>  "utf8",
		));

		$this->output = false;
		$this->htmlMode = false;

		$this->server = new mysqli($options["hostname"],$options["username"],$options["password"]);
		if($options["charset"]) {
			$this->server->set_charset($options["charset"]);
		}

		if(!$this->server) {
			$this->error();
		}

	}


	/**
	 * Get the full table name (including database)
	 */
	public function getTableName($name,$database=false) {

		if($database) {
			$table = $this->quoteField($database) . "." . $this->quoteField($name);

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

		if(!$result = $this->server->query($preparedQuery)) {
			$this->error();
		}

		if(!$result) {
			$this->error();
		}

		return $result;

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
					$val = "";

					# Interpret null values as an empty string

				default:
					$value = $this->server->real_escape_string($val);
					$value = "'" . $value . "'";
				break;
			}

			$preparedQuery .= $value;

		}

		$preparedQuery .= $tmpQuery;

		return $preparedQuery;

	}


	public function error() {

		throw new Exception($this->getError());

	}


	public function getError() {

		$errorMsg = "";

		if($this->server->connect_error) {
			$errorMsg = $this->server->connect_error . " (" . $this->server->connect_errno . ")";
		} else {
			$errorMsg = $this->server->error . " (" . $this->server->errno . ")";
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

		$query .= "WHERE " . $this->where($where,$params);

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


	public function getId($result) {

		if(!$result) {
			return false;
		}

		if(!$id = $this->server->insert_id) {
			throw new Exception("Failed to get the last inserted row id");
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

		$query = "DELETE FROM " . $tableName . "
			WHERE " . $this->where($where,$params);

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

		$row = $result->fetch_assoc();

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

		$this->seek($result,$row);
		$data = $this->fetch($result,true);
		$value = $data[$col];

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

		$query = "SELECT " . $this->selectFields($fields);

		$query .= " FROM " . $table . " ";

		$query .= "WHERE " . $this->where($where,$params);

		if($orderBy) {
			$query .= $this->orderBy($orderBy) . " ";
 		}

		$query .= "LIMIT 1";

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

		$query .= "WHERE " . $this->where($where,$params);

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

		$result->data_seek($row);

	}


	/**
	 * Quote a field with the appropriate characters
	 */
	public function quoteField($field) {

		$field = trim($field);

		$quoted = "`" . $field . "`";

		return $quoted;

	}


	/**
	 * Quote a table with the appropriate characters
	 */
	public function quoteTable($table) {

		$table = trim($table);

		$quoted = "`" . $table . "`";

		return $quoted;

	}


	/**
	 * Close the sql connection
	 */
	public function disconnect() {

		if(!$this->server) {
			return false;
		}

		$result = $this->server->close();

		return $result;

	}


	/**
	 * Automatically close the connection on destruction
	 */
	public function __destruct() {

		$this->disconnect();

	}


}
