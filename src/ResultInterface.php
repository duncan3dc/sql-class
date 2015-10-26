<?php

namespace duncan3dc\SqlClass;

interface ResultInterface extends \SeekableIterator, \Countable
{

    /**
     * Set the fetch style used by future calls to the fetch() method
     *
     * @param int $style One of the fetch style constants from the Sql class (Sql::FETCH_ROW or Sql::FETCH_ASSOC)
     *
     * @return void
     */
    public function fetchStyle($style);


    /**
     * Fetch the next row from the result set and clean it up
     *
     * All field values have rtrim() called on them to remove trailing space
     * All column keys have strtolower() called on them to convert them to lowercase (for consistency across database engines)
     *
     * @param int $style One of the fetch style constants from the Sql class (Sql::FETCH_ROW, Sql::FETCH_ASSOC or Sql::FETCH_RAW)
     *
     * @return array|Generator|null
     */
    public function fetch($style = null);


    /**
     * Get a generator function to fetch from.
     *
     * @return Generator
     */
    public function getValues();


    /**
     * Fetch an individual value from the result set
     *
     * @param int $row The index of the row to fetch (zero-based)
     * @param int $col The index of the column to fetch (zero-based)
     *
     * @return string
     */
    public function result($row, $col);


    /**
     * Seek to a specific record of the result set
     *
     * @param int $row The index of the row to position to (zero-based)
     *
     * @return void
     */
    public function seek($row);


    /**
     * Get the number of columns in the result set
     *
     * @return int
     */
    public function columnCount();


    /**
     * http://php.net/manual/en/class.countable.php
     */
    public function count();
}
