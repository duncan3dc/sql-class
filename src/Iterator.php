<?php

namespace duncan3dc\SqlClass;

abstract class Iterator implements \Iterator
{
    protected $position;

    /**
     * Fetch the next row from the result set and clean it up
     */
    abstract public function fetch($indexed = null);

    /**
     * Fetch an indiviual value from the result set
     */
    abstract public function result($row, $col);

    /**
     * Seek to a specific record of the result set
     */
    abstract public function seek($row);

    /**
     * Get the number of rows returned by the query
     */
    abstract public function rowCount();

    /**
     * Get the number of columns in the result set
     */
    abstract public function columnCount();


    final public function current()
    {
        $current = $this->fetch();
        $this->seek($this->position - 1);
        return $current;
    }

    final public function key()
    {
        return $this->position;
    }

    final public function next()
    {
        $this->fetch();
    }

    final public function rewind()
    {
        $this->seek(0);
    }

    final public function valid()
    {
        $current = $this->fetch();
        $this->seek($this->position - 1);
        return is_array($current);
    }
}
