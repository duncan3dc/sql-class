<?php
/**
 * Base class that Result/Cache extend from
 */

namespace duncan3dc\SqlClass;

abstract class AbstractResult implements \SeekableIterator, \Countable
{
    /**
     * @var int $position The current (zero based) position in the result set
     */
    protected $position;

    /**
     * @var int $fetchStyle The current fetch style (one of the Sql::FETCH_ constants)
     */
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
     * @return array|Generator|null
     */
    public function fetch($style = null)
    {
        if ($style === Sql::FETCH_GENERATOR) {
            trigger_error('Sql::fetch(Sql::FETCH_GENERATOR) is deprecated in favour of using the getValues() method, eg $result->getValues()', E_USER_DEPRECATED);
            return $this->getValues();
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
     * Get a generator function to fetch from.
     *
     * @return Generator
     */
    public function getValues()
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
     * Create an array keyed by the specified field.
     *
     * @return array
     */
    public function groupBy(...$keys)
    {
        $data = [];

        while ($row = $this->getNextRow()) {

            $position = &$data;

            foreach ($keys as $key) {
                if (is_callable($key)) {
                    $value = $key($row);
                } else {
                    $value = $row[$key];
                }

                if (!array_key_exists($value, $position)) {
                    $position[$value] = [];
                }
                $position = &$position[$value];
            }

            $position[] = $row;
            unset($position);
        }

        return $data;
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
        if ($this->position > 0) {
            $this->seek(0);
        }
    }


    /**
     * http://php.net/manual/en/class.iterator.php
     */
    final public function valid()
    {
        /**
         * Fetching is the most reliable way to check if we have a valid row.
         * Other methods were attempted, such as checking the current position against the result set count,
         * but sqlite lets us down here as the driver doesn't support count, so we have to fetch which screws everything up
         */
        $current = $this->getNextRow();

        # If we found a record, then seek back so that it can be fetched
        if (is_array($current)) {
            $this->seek($this->position - 1);
            return true;

        # If we didn't find a record then just return false
        } else {
            return false;
        }
    }
}
