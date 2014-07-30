<?php

namespace duncan3dc\SqlClass;

interface ResultInterface
{

    /**
     * Fetch the next row from the result set and clean it up
     */
    public function fetch($indexed = null);

    /**
     * Fetch an indiviual value from the result set
     */
    public function result($row, $col);

    /**
     * Seek to a specific record of the result set
     */
    public function seek($row);

    /**
     * Get the number of rows returned by the query
     */
    public function rowCount();

    /**
     * Get the number of columns in the result set
     */
    public function columnCount();
}
