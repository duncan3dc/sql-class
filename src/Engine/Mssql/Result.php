<?php

namespace duncan3dc\SqlClass\Engine\Mssql;

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
        return mssql_fetch_assoc($this->result);
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
        mssql_data_seek($this->result, $position);
    }


    /**
     * Get the number of rows in the result set.
     *
     * @return int
     */
    public function count()
    {
        return mssql_num_rows($this->result);
    }


    /**
     * Get the number of columns in the result set.
     *
     * @return int
     */
    public function columnCount()
    {
        return mssql_num_fields($this->result);
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
        return mssql_result($this->result, $row, $col);
    }


    /**
     * Free the memory used by the result resource.
     *
     * @return void
     */
    public function free()
    {
        if (is_resource($this->result)) {
            mssql_free_result($this->result);
        }
    }
}
