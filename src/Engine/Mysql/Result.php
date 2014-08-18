<?php

namespace duncan3dc\SqlClass\Engine\Mysql;

use duncan3dc\SqlClass\Engine\AbstractResult;

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
        $row = $this->result->fetch_assoc();

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
        $this->result->data_seek($position);

        $this->position = $position;
    }


    /**
     * Get the number of rows in the result set.
     *
     * @return int
     */
    public function count()
    {
        return $this->result->num_rows;
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
     * @return mixed
     */
    public function result($row, $col)
    {
        $position = $this->position;

        $this->seek($row);
        $value = $this->result->fetch_row()[$col];

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
        if ($this->result instanceof \mysqli_result) {
            $this->result->free();
        }
    }
}
