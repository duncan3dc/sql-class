<?php

namespace duncan3dc\SqlClass\Engine\Odbc;

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
        $row = odbc_fetch_array($this->result, $this->position + 1);

        if (is_array($row)) {
            ++$this->position;
        }

        return $row;
    }


    /**
     * The odbc driver doesn't support seeking, so we fetch specific rows in getNextRow().
     *
     * @param int $position The index of the row to position to (zero-based)
     *
     * @return void
     */
    public function seek($position)
    {
        $this->position = $position;
    }


    /**
     * Get the number of rows in the result set.
     *
     * @return int
     */
    public function count()
    {
        $rows = odbc_num_rows($this->result);

        # The above function is unreliable, so if we got a zero count then double check it
        if ($rows < 1) {
            $rows = 0;

            /**
             * If it is an update/delete then we just have to trust the odbc_num_rows() result,
             * however it is some kind of select, then we can manually count the rows returned.
             */
            if (odbc_num_fields($this->result) > 0) {
                $position = $this->position;
                $this->seek(0);
                while ($this->getNextRow()) {
                    ++$rows;
                }
                $this->seek($position);
            }
        }

        return $rows;
    }


    /**
     * Get the number of columns in the result set.
     *
     * @return int
     */
    public function columnCount()
    {
        return odbc_num_fields($this->result);
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
        odbc_fetch_row($this->result, $row + 1);
        return odbc_result($this->result, $col + 1);
    }


    /**
     * Free the memory used by the result resource.
     *
     * @return void
     */
    public function free()
    {
        odbc_free_result($this->result);
    }
}
