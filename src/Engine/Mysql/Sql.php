<?php

namespace duncan3dc\SqlClass\Engine\Mysql;

use duncan3dc\Helpers\Helper;
use duncan3dc\SqlClass\Result;
use duncan3dc\SqlClass\Engine\AbstractSql;
use duncan3dc\SqlClass\Sql as SqlClass;

class Sql extends AbstractSql
{
    /**
     * If we have not already connected then connect to the database now
     */
    public function connect(array $options)
    {
        $server = new \Mysqli($options["hostname"], $options["username"], $options["password"]);
        if ($server->connect_error) {
            $this->error();
        }
        if ($options["charset"]) {
            $server->set_charset($options["charset"]);
        }
        if ($timezone = $options["timezone"]) {
            if ($timezone === SqlClass::USE_PHP_TIMEZONE) {
                $timezone = ini_get("date.timezone");
            }
            $this->query("SET time_zone='" . $timezone . "'");
        }
        if ($database = $options["database"]) {
            if (!$server->select_db($database)) {
                $this->error();
            }
        }

        return $server;
    }


    /**
     * Execute an sql query
     */
    public function query($query, array $params = null, $preparedQuery)
    {
        return $this->server->query($preparedQuery);
    }


    /**
     * Replace any non-standard functions with the appropriate function for the current mode
     */
    public function functions(&$query)
    {
        $query = preg_replace("/\bISNULL\(/", "IFNULL(", $query);
        $query = preg_replace("/\bSUBSTR\(/", "SUBSTRING(", $query);
    }

    /**
     * Convert any limit usage
     */
    public function limit(&$query)
    {
        $query = preg_replace("/\bFETCH\s+FIRST\s+([0-9]+)\s+ROW(S?)\s+ONLY\b/i", "\nLIMIT $1\n", $query);
    }


    public function quoteTable($table)
    {
        return "`" . $table . "`";
    }


    public function quoteValue($string)
    {
        return "'" . $this->server->real_escape_string($string) . "'";
    }


    public function getError()
    {
        if ($this->server->connect_error) {
            return $this->server->connect_error . " (" . $this->server->connect_errno . ")";
        } else {
            return $this->server->error . " (" . $this->server->errno . ")";
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

        if ($extra == SqlClass::INSERT_REPLACE) {
            $query = "REPLACE ";
        } elseif ($extra == SqlClass::INSERT_IGNORE) {
            $query = "INSERT IGNORE ";
        } else {
            $query = "INSERT ";
        }
        $query .= "INTO " . $table . " (" . $fields . ") VALUES " . $values;

        return $this->query($query, $newParams);
    }


    public function getId(Result $result)
    {
        return $id = $this->server->insert_id;
    }


    /**
     * Start a transaction by turning autocommit off
     */
    public function startTransaction()
    {
        # Ensure we have a connection to start the transaction on
        $this->connect();

        return $this->server->autocommit(false);
    }


    /**
     * End a transaction by either committing changes made, or reverting them
     */
    public function endTransaction($commit)
    {
        return $this->server->autocommit(true);
    }


    /**
     * Commit queries without ending the transaction
     */
    public function commit()
    {
        return $this->server->commit();
    }


    /**
     * Rollback queries without ending the transaction
     */
    public function rollback()
    {
        return $this->server->rollback();
    }


    /**
     * Lock some tables for exlusive write access
     * But allow read access to other processes
     */
    public function lockTables(array $tables)
    {
        return $this->query("LOCK TABLES " . implode(",", $tables) . " WRITE");
    }


    /**
     * Unlock all tables previously locked
     */
    public function unlockTables()
    {
        return $this->query("UNLOCK TABLES");
    }


    public function getDatabases()
    {
        $databases = [];

        $result = $this->query("SHOW DATABASES");

        $result->fetchStyle(SqlClass::FETCH_ROW);
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

        $result->fetchStyle(SqlClass::FETCH_ROW);
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

        $result->fetchStyle(SqlClass::FETCH_ROW);
        foreach ($result as $row) {
            $views[] = $row[0];
        }

        return $views;
    }


    /**
     * Close the sql connection
     */
    public function disconnect()
    {
        if ($this->server->connect_error) {
            return;
        }

        return $this->server->close();
    }
}
