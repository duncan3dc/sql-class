<?php
/**
 * Base class that Result/Cache extend from
 */

namespace duncan3dc\SqlClass;

abstract class ResultInterface implements \SeekableIterator, \Countable
{
    protected $position;
    protected $fetchStyle = Sql::FETCH_ASSOC;

    /**
     * Set the fetch style used by future calls to the fetch() method
     *
     * @param int $style One of the fetch style constants from the Sql class (Sql::FETCH_ROW or Sql::FETCH_ASSOC)
     *
     * @return void
     */
    public function fetchStyle($style)
    {
        if (!in_array($style, [Sql::FETCH_ROW, Sql::FETCH_ASSOC], true)) {
            throw new \Exception("Invalid fetch style specified");
        }
        $this->fetchStyle = $style;
    }

    /**
     * Fetch the next row from the result set and clean it up
     *
     * @param int $style One of the fetch style constants from the Sql class (Sql::FETCH_ROW or Sql::FETCH_ASSOC)
     *
     * @return array|null
     */
    abstract public function fetch($style = null);

    /**
     * Fetch an indiviual value from the result set
     *
     * @param int $row The index of the row to fetch (zero-based)
     * @param int $col The index of the column to fetch (zero-based)
     *
     * @return string
     */
    abstract public function result($row, $col);

    /**
     * Seek to a specific record of the result set
     *
     * @param int $row The index of the row to position to (zero-based)
     *
     * @return void
     */
    abstract public function seek($row);

    /**
     * Get the number of columns in the result set
     *
     * @return int
     */
    abstract public function columnCount();

    /**
     * http://php.net/manual/en/class.iterator.php
     */
    final public function current()
    {
        $current = $this->fetch();
        $this->seek($this->position - 1);
        return $current;
    }

    /**
     * http://php.net/manual/en/class.iterator.php
     */
    final public function key()
    {
        return $this->position;
    }

    /**
     * http://php.net/manual/en/class.iterator.php
     */
    final public function next()
    {
        $this->fetch();
    }

    /**
     * http://php.net/manual/en/class.iterator.php
     */
    final public function rewind()
    {
        $this->seek(0);
    }

    /**
     * http://php.net/manual/en/class.iterator.php
     */
    final public function valid()
    {
        $current = $this->fetch();
        $this->seek($this->position - 1);
        return is_array($current);
    }
}