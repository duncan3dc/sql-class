<?php

namespace duncan3dc\SqlClass\Query;

use duncan3dc\Helpers\Helper;
use duncan3dc\SqlClass\Exceptions\QueryException;
use duncan3dc\SqlClass\Where\AbstractWhere;

/**
 * Generate queries using methods.
 */
class Builder
{
    /**
     * @var int A regular SELECT statement.
     */
    const TYPE_SELECT = 1;

    /**
     * @var int An INSERT statement.
     */
    const TYPE_INSERT = 2;

    /**
     * @var int An UPDATE statement.
     */
    const TYPE_UPDATE = 3;

    /**
     * @var int A DELETE statement.
     */
    const TYPE_DELETE = 4;

    /**
     * @var int $type The type of query to build.
     */
    protected $type;

    /**
     * @var string $table The name of the table to perform the query over.
     */
    protected $table;

    /**
     * @var array $where A Multi dimensional array containing the where clause.
     */
    protected $where = [];

    /**
     * @var int $whereIndex The current position in the where clause.
     */
    protected $whereIndex = 0;


    public function __construct($type)
    {
        $this->type = $type;
    }

    public function table($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Get the full table name (including database).
     *
     * @return string
     */
    protected function getTable()
    {
        $database = $this->getTableDatabase($this->table);

        if ($database) {
            $table = $this->quoteField($database) . "." . $this->quoteField($this->table);
        } else {
            if (strpos($this->table, ".") === false) {
                $table = $this->quoteField($this->table);
            } else {
                $table = $this->table;
            }
        }

        return $table;
    }


    public function where($field, $value = null)
    {
        if (is_array($field)) {
            foreach ($field as $key => $val) {
                $this->where($key, $val);
            }
            return $this;
        }

        if (!$value instanceof AbstractWhere) {
            $value = new Where\Equals($value);
        }

        if (!is_array($this->where[$this->whereIndex])) {
            $this->where[$this->whereIndex] = [
                "type"      =>  "AND",
                "fields"    =>  [],
            ];
        }
        $this->where[$this->whereIndex]["fields"][] = [$field, $value];
    }

    public function __call($method, $params)
    {
        if ($method === "and") {
            $this->whereIndex = count($this->where);
            return $this->where(...$params);
        }

        if ($method === "or") {
            $this->whereIndex = count($this->where);
            $this->where[$this->whereIndex] = [
                "type"      =>  "OR",
                "fields"    =>  [],
            ];
            return $this->where(...$params);
        }

        throw new \BadMethodCall("Invalid method: " . $method);
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

    /**
     * Convert an array of parameters into a valid where clause
     */
    protected function addWhereClause(&$query)
    {
        $params = [];

        $whereFlag = false;

        foreach ($this->where as $section) {

            # Add the where flag if this isn't the first section
            if ($whereFlag) {
                $query .= "WHERE ";
            } else {
                $query .= $section["type"] . " ";
                $whereFlag = true;
            }

            $query .= "(";

            $andFlag = false;

            foreach ($section["fields"] as list($field, $value)) {

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
        }

        return $params;
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
        $class = __NAMESPACE__ . "\\Where\\" . ucfirst($method);
        if (class_exists($class)) {
            return new $class(...$params);
        }
        throw new \Exception("Invalid method: " . $method);
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
