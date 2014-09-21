<?php

namespace duncan3dc\SqlClass\Engine\Sqlite;

use duncan3dc\SqlClass\Engine\AbstractResult;
use duncan3dc\SqlClass\Exceptions\QueryException;

class Result extends AbstractResult
{
    protected $position = 0;

    /**
     * Internal method to fetch the next row from the result set.
     *
     * @return array|null
     */
    public function getNextRow()
    {
        $row = $this->result->fetchArray(\SQLITE3_ASSOC);

        if (is_array($row)) {
            ++$this->position;
        }

        return $row;
    }


    /**
     * Seek to a specific record of the result set.
     *
     * @param int $position The index of the row to position to (zero-based)
     *
     * @return void
     */
    public function seek($position)
    {
        $this->result->reset();
        for ($i = 0; $i < $position; $i++) {
            if (!$this->result->fetchArray(\SQLITE3_NUM)) {
                throw new QueryException("Unable to seek to row {$position}");
            }
        }

        $this->position = $position;
    }


    /**
     * Get the number of rows in the result set.
     *
     * @return int
     */
    public function count()
    {
        $rows = 0;

        $position = $this->position;
        $this->seek(0);

        while ($this->result->fetchArray(\SQLITE3_NUM)) {
            ++$rows;
        }

        $this->seek($position);

        return $rows;
    }


    /**
     * Get the number of columns in the result set.
     *
     * @return int
     */
    public function columnCount()
    {
        return $this->result->numColumns();
    }


    /**
     * Fetch an indiviual value from the result set.
     *
     * @param int $row The index of the row to fetch (zero-based)
     * @param int $col The index of the column to fetch (zero-based)
     *
     * @return string
     */
    public function result($row, $col)
    {
        $position = $this->position;

        $this->seek($row);

        $value = $this->result->fetchArray(\SQLITE3_NUM)[$col];

        $this->seek($position);

        return $value;
    }


    /**
     * Free the memory used by the result resource.
     *
     * @return void
     */
    public function free()
    {
        $this->result->finalize();
    }
}
