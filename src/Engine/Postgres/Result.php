<?php

namespace duncan3dc\SqlClass\Engine\Postgres;

use duncan3dc\SqlClass\Engine\AbstractResult;

class Result extends AbstractResult
{

    /**
     * Internal method to fetch the next row from the result set.
     *
     * @return array|null
     */
    public function getNextRow()
    {
        return pg_fetch_assoc($this->result);
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
        pg_result_seek($this->result, $position);
    }


    /**
     * Get the number of rows in the result set.
     *
     * @return int
     */
    public function count()
    {
        return pg_num_rows($this->result);
    }


    /**
     * Get the number of columns in the result set.
     *
     * @return int
     */
    public function columnCount()
    {
        return $this->result->field_count;
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
        return pg_fetch_result($this->result, $row, $col);
    }


    /**
     * Free the memory used by the result resource.
     *
     * @return void
     */
    public function free()
    {
        pg_free_result($this->result);
    }
}
