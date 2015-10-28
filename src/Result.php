<?php
/**
 * Result class for reading rows for a result set
 */

namespace duncan3dc\SqlClass;

class Result extends AbstractResult
{
    /**
     * @var resource $result The result resource
     */
    public  $result;

    /**
     * @var string $mode The type of database this result set is for
     */
    public  $mode;


    /**
     * Create a Result instance to provide extra functionality
     *
     * @param mixed $result The result resouce returned by executed a query
     * @param string $mode The Sql engine mode that this result was generated by
     *
     * @return void
     */
    public function __construct($result, $mode)
    {
        $this->position = 0;
        $this->result = $result;
        $this->mode = $mode;
    }


    /**
     * Internal method to fetch the next row from the result set
     *
     * @return array|null
     */
    protected function getNextRow()
    {
        # If the result resource is invalid then don't bother trying to fetch
        if (!$this->result) {
            return;
        }

        switch ($this->mode) {

            case "mysql":
                $row = $this->result->fetch_assoc();
                break;

            case "postgres":
            case "redshift":
                $row = pg_fetch_assoc($this->result);
                break;

            case "odbc":
                $row = odbc_fetch_array($this->result, $this->position + 1);
                break;

            case "sqlite":
                $row = $this->result->fetchArray(SQLITE3_ASSOC);
                break;

            case "mssql":
                $row = mssql_fetch_assoc($this->result);
                break;
        }

        # If the fetch fails then there are no rows left to retrieve
        if (!$row) {
            return;
        }

        $this->position++;

        return $row;
    }


    /**
     * Old method of a raw fetch
     *
     * @deprecated use fetch(Sql::FETCH_RAW) instead
     */
    public function _fetch()
    {
        trigger_error('Result::_fetch() is deprecated in favour of using the raw style fetch, eg $result->fetch(Sql::FETCH_RAW)', E_USER_DEPRECATED);
        return $this->fetch(FETCH_RAW);
    }


    /**
     * Fetch an indiviual value from the result set
     *
     * @param int $row The index of the row to fetch (zero-based)
     * @param int $col The index of the column to fetch (zero-based)
     *
     * @return string
     */
    public function result($row, $col)
    {
        if (!$this->result) {
            return false;
        }

        switch ($this->mode) {

            case "mysql":
            case "sqlite":
                $this->seek($row);
                $data = $this->fetch(true);
                $value = $data[$col];
                $this->seek($this->position);
                break;

            case "postgres":
            case "redshift":
                $value = pg_fetch_result($this->result, $row, $col);
                break;

            case "odbc":
                odbc_fetch_row($this->result, $row + 1);
                $value = odbc_result($this->result, $col + 1);
                break;

            case "mssql":
                $value = mssql_result($this->result, $row, $col);
                break;
        }

        $value = rtrim($value);

        return $value;
    }


    /**
     * Seek to a specific record of the result set
     *
     * @param int $row The index of the row to position to (zero-based)
     *
     * @return void
     */
    public function seek($row)
    {

        switch ($this->mode) {

            case "mysql":
                $this->result->data_seek($row);
                break;

            case "postgres":
            case "redshift":
                pg_result_seek($this->result, $row);
                break;

            case "odbc":
                # The odbc driver doesn't support seeking, so we fetch specific rows in getNextRow(), and here all we need to do is set the current position instance variable
                break;

            case "sqlite":
                $this->result->reset();
                for ($i = 0; $i < $row; $i++) {
                    $this->result->fetchArray();
                }
                break;

            case "mssql":
                mssql_data_seek($this->result, $row);
                break;
        }

        $this->position = $row;
    }


    /**
     * Get the number of rows in the result set
     *
     * @return int
     */
    public function count()
    {

        switch ($this->mode) {

            case "mysql":
                $rows = $this->result->num_rows;
                break;

            case "postgres":
            case "redshift":
                $rows = pg_num_rows($this->result);
                break;

            case "odbc":
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
                break;

            case "sqlite":
                $rows = 0;
                while ($this->result->fetchArray()) {
                    $rows++;
                }
                $this->seek($this->position);
                break;

            case "mssql":
                $rows = mssql_num_rows($this->result);
                break;
        }

        if ($rows === false || $rows < 0) {
            throw new \Exception("Failed to get the row count from the result set");
        }

        return $rows;
    }


    /**
     * Get the number of columns in the result set
     *
     * @return int
     */
    public function columnCount()
    {

        switch ($this->mode) {

            case "mysql":
                $columns = $this->result->field_count;
                break;

            case "postgres":
            case "redshift":
                $columns = pg_num_fields($this->result);
                break;

            case "odbc":
                $columns = odbc_num_fields($this->result);
                break;

            case "sqlite":
                $columns = $this->result->numColumns();
                break;

            case "mssql":
                $columns = mssql_num_fields($this->result);
                break;
        }

        if ($columns === false || $columns < 0) {
            throw new \Exception("Failed to get the column count from the result set");
        }

        return $columns;
    }


    /**
     * Free the memory used by the result resource
     *
     * @return void
     */
    public function free()
    {

        switch ($this->mode) {

            case "mysql":
                $this->result->free();
                break;

            case "postgres":
            case "redshift":
                pg_free_result($this->result);
                break;

            case "odbc":
                odbc_free_result($this->result);
                break;

            case "sqlite":
                $this->result->finalize();
                break;

            case "mssql":
                mssql_free_result($this->result);
                break;
        }
    }


    /**
     * If the result source is still available then free it before tearing down the object
     *
     * @return void
     */
    public function __destruct()
    {
        if (is_bool($this->result)) {
            return;
        }

        $this->free();
    }
}
