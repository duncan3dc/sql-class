<?php

namespace duncan3dc\SqlClass;

/**
 * Handle queries for an individual table.
 */
class Table
{
    /**
     * @var string $table The name of the table to query.
     */
    protected $table;

    /**
     * @var Sql $sql The Sql instance to run queries via.
     */
    protected $sql;

    /**
     * Create a new instance.
     *
     * @param Sql $sql The Sql instance to insert the records via
     * @param string $table The name of the table to insert into
     * @param int $limit The maximum number of rows to insert at a time
     */
    public function __construct($table, Sql $sql)
    {
        $this->table = (string) $table;
        $this->sql = $sql;
    }


    public function insertChunks()
    {
        $this->bulk = new BulkInsert();

        return $this;
    }


    public function cache()
    {
        return $this->cacheNext();
    }


    public function cacheNext()
    {
        $this->cacheAll = false;
        $this->cacheNext = true;
        return $this;
    }


    public function cacheAll()
    {
        $this->cacheAll = true;
        $this->cacheNext = true;
        return $this;
    }


    public function update(array $set, $where)
    {
        $query = "UPDATE {$this->table} SET ";

        $params = [];
        foreach ($set as $key => $val) {
            $query .= $this->quoteField($key) . "=?,";
            $params[] = $val;
        }

        $query = substr($query, 0, -1) . " ";

        if ($where !== self::NO_WHERE_CLAUSE) {
            $query .= "WHERE " . $this->sql->where($where, $params);
        }

        $result = $this->query($query, $params);

        return $result;
    }


    public function insert(array $params, $extra = null)
    {
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
        $query .= "INTO {$this->table} ({$fields}) VALUES ({$values})";

        $result = $this->query($query, $newParams);

        return $result;
    }


    public function bulkInsert(array $params, $extra = null)
    {
        # Ensure we have a connection to run this query on
        $this->connect();

        $logger = null;
        if (!$this->logger instanceof NullLogger) {
            $this->logger->debug("BULK INSERT INTO {table} ({rows} rows)", [
                "table" =>  $this->table,
                "rows"  =>  count($params),
            ]);
            $this->logger = new NullLogger;
        }

        $result = $this->engine->bulkInsert($this->table, $params, $extra);

        if ($logger) {
            $this->logger = $logger;
        }

        if (!$result instanceof ResultInterface) {
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


    public function delete($where)
    {
        $params = null;

        /**
         * If this is a complete empty of the table then the TRUNCATE TABLE statement is a lot faster than issuing a DELETE statement.
         * This statement is not transaction safe, so if we are currently in a transaction then we do not issue the TRUNCATE statement.
         * Also not all engines support this though, so we need to check that too.
         */
        if ($where === self::NO_WHERE_CLAUSE && !$this->transaction && $this->engine->canTruncateTables()) {
            $query = "TRUNCATE TABLE {$this->table}";
        } else {
            $query = "DELETE FROM {$this->table} ";

            if ($where !== self::NO_WHERE_CLAUSE) {
                $query .= "WHERE " . $this->sql->where($where, $params);
            }
        }

        return $this->query($query, $params);
    }


    /**
     * Grab the first row from a table using the standard select statement
     * This is a convience method for a fieldSelect() where all fields are required
     */
    public function select($where, $orderBy = null)
    {
        return $this->fieldSelect("*", $where, $orderBy);
    }


    /**
     * Grab specific fields from the first row from a table using the standard select statement
     */
    public function fieldSelect($fields, $where, $orderBy = null)
    {
        $query = "SELECT ";

        if ($this->engine instanceof Engine\Mssql\Server) {
            $query .= "TOP 1 ";
        }

        $query .= $this->sql->selectFields($fields);

        $query .= " FROM {$this->table} ";

        $params = null;
        if ($where !== self::NO_WHERE_CLAUSE) {
            $query .= "WHERE " . $this->sql->where($where, $params);
        }

        if ($orderBy) {
            $query .= $this->sql->orderBy($orderBy) . " ";
        }

        if ($this->engine instanceof Engine\Odbc\Server) {
            $query .= "FETCH FIRST 1 ROW ONLY";
        } elseif (!$this->engine instanceof Engine\Mssql\Server) {
            $query .= "LIMIT 1";
        }

        return $this->query($query, $params)->fetch();
    }


    /**
     * Create a standard select statement and return the result
     * This is a convience method for a fieldSelectAll() where all fields are required
     */
    public function selectAll($where, $orderBy = null)
    {
        return $this->fieldSelectAll("*", $where, $orderBy);
    }


    /**
     * Create a standard select statement and return the result
     */
    public function fieldSelectAll($fields, $where, $orderBy = null)
    {
        $query = "SELECT ";

        $query .= $this->sql->selectFields($fields);

        $query .= " FROM {$this->table} ";

        $params = null;
        if ($where !== self::NO_WHERE_CLAUSE) {
            $query .= "WHERE " . $this->sql->where($where, $params);
        }

        if ($orderBy) {
            $query .= $this->sql->orderBy($orderBy) . " ";
        }

        return $this->query($query, $params);
    }


    /**
     * Check if a record exists without fetching any data from it.
     *
     * @param array|int $where The where clause to use, or the NO_WHERE_CLAUSE constant
     *
     * @return boolean Whether a matching row exists in the table or not
     */
    public function exists($where)
    {
        return (bool) $this->fieldSelect("1", $where);
    }


    /**
     * Insert a new record into a table, unless it already exists in which case update it
     */
    public function insertOrUpdate(array $set, array $where)
    {
        if ($this->select($where)) {
            return $this->update($set, $where);
        }

        $params = array_merge($where, $set);
        return $this->insert($params);
    }
}
