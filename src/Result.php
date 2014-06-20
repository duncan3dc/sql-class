<?php

namespace duncan3dc\SqlClass;

class Result {

    public  $result;    # The result resource
    public  $mode;      # The type of database this result set is for


    public function __construct($result,$mode) {

        $this->result = $result;
        $this->mode = $mode;

    }


    /**
     * Fetch the next row from the result set
     */
    public function _fetch() {

        # If the result resource is invalid then don't bother trying to fetch
        if(!$this->result) {
            return false;
        }

        switch($this->mode) {

            case "mysql":
                $row = $this->result->fetch_assoc();
            break;

            case "postgres":
            case "redshift":
                $row = pg_fetch_assoc($this->result);
            break;

            case "odbc":
                $row = odbc_fetch_array($this->result);
            break;

            case "sqlite":
                $row = $this->result->fetchArray(SQLITE3_ASSOC);
            break;

            case "mssql":
                $row = mssql_fetch_assoc($this->result);
            break;

        }

        # If the fetch fails then there are no rows left to retrieve
        if(!$row) {
            return false;
        }

        return $row;

    }


    /**
     * Fetch the next row from the result set and clean it up
     */
    public function fetch($indexed=false) {

        # If the fetch fails then there are no rows left to retrieve
        if(!$data = $this->_fetch($this->result)) {
            return false;
        }

        $row = [];

        foreach($data as $key => $val) {

            $val = rtrim($val);

            # If the data should be returned as an enumerated array then ignore the key
            if($indexed) {
                $row[] = $val;

            } else {
                $key = strtolower($key);
                $row[$key] = $val;

            }

        }

        return $row;

    }


    /**
     * Fetch an indiviual value from the result set
     */
    public function result($row,$col) {

        if(!$this->result) {
            return false;
        }

        switch($this->mode) {

            case "mysql":
            case "sqlite":
                $this->seek($row);
                $data = $this->fetch(true);
                $value = $data[$col];
            break;

            case "postgres":
            case "redshift":
                $value = pg_fetch_result($this->result,$row,$col);
            break;

            case "odbc":
                odbc_fetch_row($this->result,($row + 1));
                $value = odbc_result($this->result,($col + 1));
            break;

            case "mssql":
                $value = mssql_result($this->result,$row,$col);
            break;

        }

        $value = rtrim($value);

        return $value;

    }


    /**
     * Seek to a specific record of the result set
     */
    public function seek($row) {

        switch($this->mode) {

            case "mysql":
                $this->result->data_seek($row);
            break;

            case "postgres":
            case "redshift":
                pg_result_seek($this->result,$row);
            break;

            case "odbc":
                # This actually does a seek and fetch, so although the rows are numbered 1 higher than other databases, this will still work
                odbc_fetch_row($this->result,$row);
            break;

            case "sqlite":
                $this->result->reset();
                for($i = 0; $i < $row; $i++) {
                    $this->fetch($this->result);
                }
            break;

            case "mssql":
                mssql_data_seek($this->result,$row);
            break;

        }

    }


    public function rowCount() {

        switch($this->mode) {

            case "mysql":
                $rows = $this->result->num_rows;
            break;

            case "postgres":
            case "redshift":
                $rows = pg_num_rows($this->result);
            break;

            case "odbc":
                $rows = odbc_num_rows($this->result);
            break;

            case "sqlite":
                $rows = 0;
                while($this->fetch) {
                    $rows++;
                }
                $this->result->reset();
            break;

            case "mssql":
                $columns = mssql_num_rows($this->result);
            break;

        }

        if($rows === false || $rows < 0) {
            throw new \Exception("Failed to get the row count from the result set");
        }

        return $rows;

    }


    public function columnCount() {

        switch($this->mode) {

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
                $columns = $this->result->numColumns;
            break;

            case "mssql":
                $columns = mssql_num_fields($this->result);
            break;

        }

        if($columns === false || $columns < 0) {
            throw new \Exception("Failed to get the column count from the result set");
        }

        return $columns;

    }


    public function free() {

        switch($this->mode) {

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


    public function __destruct() {
        $this->free();
    }


}
