<?php

namespace duncan3dc\SqlClass\Engine\Sqlite;

use duncan3dc\SqlClass\Engine\AbstractServer;
use duncan3dc\SqlClass\Exceptions\NotImplementedException;
use duncan3dc\SqlClass\Result as ResultInterface;
use duncan3dc\SqlClass\Sql;

class Server extends AbstractServer
{
    /**
     * @var string $database The filename containing the database.
     */
    protected $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    /**
     * Check if this server supports the TRUNCATE TABLE statement.
     *
     * @return bool
     */
    public function canTruncateTables()
    {
        return false;
    }


    /**
     * Get the quote characters that this engine uses for quoting identifiers.
     *
     * @return string
     */
    public function getQuoteChars()
    {
        return '`';
    }


    public function connect()
    {
        return new \Sqlite3($this->database);
    }


    public function query($query, array $params = null, $preparedQuery)
    {
        if (!is_array($params)) {
            if ($result = $this->server->query($preparedQuery)) {
                return new Result($result);
            }
            return;
        }

        # If we have some parameters then we must convert them to the sqlite format
        $newQuery = "";
        foreach ($params as $key => $val) {
            $pos = strpos($query, "?");
            $newQuery .= substr($query, 0, $pos);
            $query = substr($query, $pos + 1);

            $newQuery .= ":var" . $key;
        }
        $newQuery .= $query;

        if (!$statement = $this->server->prepare($newQuery)) {
            return;
        }

        foreach ($params as $key => $val) {
            switch (gettype($val)) {

                case "boolean":
                case "integer":
                    $type = SQLITE3_INTEGER;
                    break;

                case "double":
                    $type = SQLITE3_FLOAT;
                    break;

                case "NULL":
                    if ($this->allowNulls) {
                        $type = SQLITE3_NULL;
                    } else {
                        $type = SQLITE3_TEXT;
                        $val = "";
                    }
                    break;

                default:
                    $type = SQLITE3_TEXT;
            }

            $statement->bindValue(":var" . $key, $val, $type);
        }

        if ($result = $statement->execute()) {
            return new Result($result);
        }
    }


    public function changeQuerySyntax($query)
    {
        $query = preg_replace("/\bISNULL\(/", "IFNULL(", $query);
        $query = preg_replace("/\bSUBSTRING\(/", "SUBSTR(", $query);
        $query = preg_replace("/\bFETCH\s+FIRST\s+([0-9]+)\s+ROW(S?)\s+ONLY\b/i", "\nLIMIT $1\n", $query);
        return $query;
    }


    public function quoteTable($table)
    {
        return "`" . $table . "`";
    }


    public function quoteField($field)
    {
        return "`" . $field . "`";
    }


    public function quoteValue($value)
    {
        $this->sql->connect();

        return "'" . $this->server->escapeString($value) . "'";
    }


    public function getErrorCode()
    {
        return $this->server->lastErrorCode();
    }


    public function getErrorMessage()
    {
        return $this->server->lastErrorMsg();
    }


    public function getId(ResultInterface $result)
    {
        return $this->sql->query("SELECT last_insert_rowid()")->fetch(Sql::FETCH_ROW)[0];
    }


    public function getDatabases()
    {
        throw new NotImplementedException("getDatabases() not available in this mode");
    }


    public function getTables($database)
    {
        throw new NotImplementedException("getTables() not available in this mode");
    }


    public function getViews($database)
    {
        throw new NotImplementedException("getViews() not available in this mode");
    }


    public function startTransaction()
    {
        throw new NotImplementedException("startTransaction() not available in this mode");
    }


    public function endTransaction()
    {
        throw new NotImplementedException("endTransaction() not available in this mode");
    }


    public function commit()
    {
        throw new NotImplementedException("commit() not available in this mode");
    }


    public function rollback()
    {
        throw new NotImplementedException("rollback() not available in this mode");
    }


    public function lockTables(array $tables)
    {
        throw new NotImplementedException("lockTables() not available in this mode");
    }


    public function unlockTables()
    {
        throw new NotImplementedException("unlockTables() not available in this mode");
    }


    public function disconnect()
    {
        if (!$this->server) {
            return;
        }

        return $this->server->close();
    }
}
