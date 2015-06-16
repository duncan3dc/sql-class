<?php

namespace duncan3dc\SqlClass\Engine\Mysql;

use duncan3dc\SqlClass\Engine\AbstractServer;
use duncan3dc\SqlClass\Result as ResultInterface;
use duncan3dc\SqlClass\Sql;

class Server extends AbstractServer
{

    /**
     * Get the quote characters that this engine uses for quoting identifiers.
     *
     * @return string
     */
    public function getQuoteChars()
    {
        return "`";
    }


    public function connect(array $options)
    {
        $server = new \Mysqli($options["hostname"], $options["username"], $options["password"]);
        if ($server->connect_error) {
            return;
        }

        $server->options(\MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);

        if ($options["charset"]) {
            $server->set_charset($options["charset"]);
        }

        if ($timezone = $options["timezone"]) {
            if ($timezone === Sql::USE_PHP_TIMEZONE) {
                $timezone = ini_get("date.timezone");
            }
            $this->query("SET time_zone='" . $timezone . "'");
        }

        if ($database = $options["database"]) {
            if (!$server->select_db($database)) {
                return;
            }
        }

        return $server;
    }


    public function query($query, array $params = null, $preparedQuery)
    {
        if ($result = $this->server->query($preparedQuery)) {
            return new Result($result);
        }
    }


    public function changeQuerySyntax($query)
    {
        $query = preg_replace("/\bISNULL\(/", "IFNULL(", $query);
        $query = preg_replace("/\bSUBSTR\(/", "SUBSTRING(", $query);
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
        return "'" . $this->server->real_escape_string($value) . "'";
    }


    public function getErrorCode()
    {
        if ($this->server->connect_error) {
            return $this->server->connect_errno;
        } else {
            return $this->server->errno;
        }
    }


    public function getErrorMessage()
    {
        if ($this->server->connect_error) {
            return $this->server->connect_error;
        } else {
            return $this->server->error;
        }
    }


    public function bulkInsert($table, array $params, $extra = null)
    {
        $fields = "";
        $first = reset($params);
        foreach ($first as $key => $val) {
            if ($fields) {
                $fields .= ",";
            }
            $fields .= $this->quoteField($key);
        }

        $newParams = [];
        $values = "";

        foreach ($params as $row) {
            if ($values) {
                $values .= ",";
            }
            $values .= "(";
            $first = true;

            foreach ($row as $key => $val) {
                if ($first) {
                    $first = false;
                } else {
                    $values .= ",";
                }
                $values .= "?";
                $newParams[] = $val;
            }
            $values .= ")";
        }

        if ($extra == Sql::INSERT_REPLACE) {
            $query = "REPLACE ";
        } elseif ($extra == Sql::INSERT_IGNORE) {
            $query = "INSERT IGNORE ";
        } else {
            $query = "INSERT ";
        }
        $query .= "INTO " . $table . " (" . $fields . ") VALUES " . $values;

        return $this->query($query, $newParams);
    }


    public function getId(ResultInterface $result)
    {
        return $id = $this->server->insert_id;
    }


    public function startTransaction()
    {
        return $this->server->autocommit(false);
    }


    public function endTransaction()
    {
        return $this->server->autocommit(true);
    }


    public function commit()
    {
        return $this->server->commit();
    }


    public function rollback()
    {
        return $this->server->rollback();
    }


    public function lockTables(array $tables)
    {
        return $this->query("LOCK TABLES " . implode(",", $tables) . " WRITE");
    }


    public function unlockTables()
    {
        return $this->query("UNLOCK TABLES");
    }


    public function getDatabases()
    {
        $databases = [];

        $result = $this->query("SHOW DATABASES");

        $result->fetchStyle(Sql::FETCH_ROW);
        foreach ($result as $row) {
            $databases[] = $row[0];
        }

        return $databases;
    }


    public function getTables($database)
    {
        $tables = [];

        $query = "SHOW FULL TABLES IN " . $this->quoteTable($database) . " WHERE table_type='BASE TABLE'";
        $result = $this->query($query);

        $result->fetchStyle(Sql::FETCH_ROW);
        foreach ($result as $row) {
            $tables[] = $row[0];
        }

        return $tables;
    }


    public function getViews($database)
    {
        $views = [];

        $query = "SHOW FULL TABLES IN " . $this->quoteTable($database) . " WHERE table_type='VIEW'";
        $result = $this->query($query);

        $result->fetchStyle(Sql::FETCH_ROW);
        foreach ($result as $row) {
            $views[] = $row[0];
        }

        return $views;
    }


    public function disconnect()
    {
        if (!$this->server || $this->server->connect_error) {
            return;
        }

        return $this->server->close();
    }
}
