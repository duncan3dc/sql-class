<?php

namespace duncan3dc\SqlClass;

/**
 * Result class for reading rows for a result set.
 */
class Result extends AbstractResult
{
    /**
     * @var ResultInterface $engine The instance of the engine class handling the abstraction
     */
    protected $engine;

    /**
     * Create a Result instance to provide extra functionality
     *
     * @param ResultInterface $engine The engine's Result instance
     */
    public function __construct(ResultInterface $engine)
    {
        $this->engine = $engine;
    }


    /**
     * Internal method to fetch the next row from the result set
     *
     * @return array|null
     */
    protected function getNextRow()
    {
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
        $value = $this->engine->result($row, $col);

        if (is_string($value)) {
            $value = rtrim($value);
        }

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
        $this->free();
    }
}
