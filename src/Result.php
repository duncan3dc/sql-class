<?php

namespace duncan3dc\SqlClass;

/**
 * Result class for reading rows for a result set.
 */
class Result extends AbstractResult
{
    /**
     * @var resource $result The result resource
     */
    public  $result;

    /**
     * @var string $mode The type of database this result set is for
     */
    public  $mode;

    /**
     * @var duncan3dc\SqlClass\Engine\AbstractResult $engine The instance of the engine class handling the abstraction
     */
    protected $engine;

    /**
     * Create a Result instance to provide extra functionality
     *
     * @param mixed $result The result resouce returned by executed a query
     * @param string $mode The Sql engine mode that this result was generated by
     */
    public function __construct($result, $mode)
    {
        $this->position = 0;
        $this->result = $result;
        $this->mode = $mode;

        $class = __NAMESPACE__ . "\\Engine\\" . ucfirst($this->mode) . "\\Result";
        $this->engine = new $class($result);
    }


    /**
     * Internal method to fetch the next row from the result set
     *
     * @return array|null
     */
    protected function getNextRow()
    {
        # If the result resource is invalid then don't bother trying to fetch
        if (!$this->result) {
            return;
        }

        $row = $this->engine->getNextRow();

        # If the fetch fails then there are no rows left to retrieve
        if (!is_array($row)) {
            return;
        }

        $this->position++;

        return $row;
    }


    /**
     * Fetch an indiviual value from the result set
     *
     * @param int $row The index of the row to fetch (zero-based)
     * @param int $col The index of the column to fetch (zero-based)
     *
     * @return string
     */
    public function result($row, $col)
    {
        if (!$this->result) {
            return false;
        }

        $value = $this->engine->result($row, $col);

        $value = rtrim($value);

        return $value;
    }


    /**
     * Seek to a specific record of the result set
     *
     * @param int $row The index of the row to position to (zero-based)
     *
     * @return void
     */
    public function seek($row)
    {
        $this->engine->seek($row);
        $this->position = $row;
    }


    /**
     * Get the number of rows in the result set
     *
     * @return int
     */
    public function count()
    {
        $rows = $this->engine->count();

        if (!is_int($rows) || $rows < 0) {
            throw new \Exception("Failed to get the row count from the result set");
        }

        return $rows;
    }


    /**
     * Get the number of columns in the result set
     *
     * @return int
     */
    public function columnCount()
    {
        $columns = $this->engine->columnCount();

        if (!is_int($columns) || $columns < 0) {
            throw new \Exception("Failed to get the column count from the result set");
        }

        return $columns;
    }


    /**
     * Free the memory used by the result resource
     *
     * @return void
     */
    public function free()
    {
        $this->engine->free();
    }


    /**
     * If the result source is still available then free it before tearing down the object
     *
     * @return void
     */
    public function __destruct()
    {
        if (is_bool($this->result)) {
            return;
        }

        $this->free();
    }
}
