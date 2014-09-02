<?php
/**
 * Base class that Result/Cache extend from
 */

namespace duncan3dc\SqlClass;

abstract class AbstractResult implements \SeekableIterator, \Countable
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
        if (!in_array($style, [Sql::FETCH_ROW, Sql::FETCH_ASSOC, Sql::FETCH_RAW], true)) {
            throw new \Exception("Invalid fetch style specified");
        }
        $this->fetchStyle = $style;
    }


    /**
     * Fetch the next row from the result set and clean it up
     *
     * All field values have rtrim() called on them to remove trailing space
     * All column keys have strtolower() called on them to convert them to lowercase (for consistency across database engines)
     *
     * @param int $style One of the fetch style constants from the Sql class (Sql::FETCH_ROW, Sql::FETCH_ASSOC or Sql::FETCH_RAW)
     *
     * @return array|null
     */
    public function fetch($style = null)
    {
        if ($style === Sql::FETCH_GENERATOR) {
            return $this->generator();
        }

        # If the fetch fails then there are no rows left to retrieve
        if (!$data = $this->getNextRow()) {
            return;
        }

        # If no style was specified then use the current setting
        if (!$style) {
            $style = $this->fetchStyle;
        }

        if ($style === Sql::FETCH_RAW) {
            return $data;
        }

        $row = [];

        foreach ($data as $key => $val) {
            $val = rtrim($val);

            if ($style === Sql::FETCH_ASSOC) {
                $key = strtolower($key);
                $row[$key] = $val;
            } else {
                $row[] = $val;
            }
        }

        return $row;
    }


    /**
     * Get a generator function to fetch from
     */
    public function generator()
    {
        while ($row = $this->getNextRow()) {
            if ($this->columnCount() > 1) {
                $key = rtrim(reset($row));
                $val = rtrim(next($row));
                yield $key => $val;
            } else {
                yield rtrim(reset($row));
            }
        }
    }


    /**
     * Internal method to fetch the next row from the result set
     *
     * @return array|null
     */
    abstract protected function getNextRow();


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
