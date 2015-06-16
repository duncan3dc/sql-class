<?php

namespace duncan3dc\SqlClass\Engine;

interface ResultInterface
{

    /**
     * Internal method to fetch the next row from the result set.
     *
     * @return array|null
     */
    public function getNextRow();


    /**
     * Seek to a specific record of the result set.
     *
     * @param int $position The index of the row to position to (zero-based)
     *
     * @return void
     */
    public function seek($position);


    /**
     * Get the number of rows in the result set.
     *
     * @return int
     */
    public function count();


    /**
     * Get the number of columns in the result set.
     *
     * @return int
     */
    public function columnCount();


    /**
     * Fetch an indiviual value from the result set.
     *
     * @param int $row The index of the row to fetch (zero-based)
     * @param int $col The index of the column to fetch (zero-based)
     *
     * @return mixed
     */
    public function result($row, $col);


    /**
     * Free the memory used by the result resource.
     *
     * @return void
     */
    public function free();
}
