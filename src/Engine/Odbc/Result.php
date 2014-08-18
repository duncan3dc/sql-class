<?php

namespace duncan3dc\SqlClass\Engine\Odbc;

use duncan3dc\SqlClass\Engine\AbstractResult;

class Result extends AbstractResult
{

    public function getNextRow()
    {
        return odbc_fetch_array($this->result, $this->position + 1);
    }


    public function result($row, $col)
    {
        odbc_fetch_row($this->result, $row + 1);
        return odbc_result($this->result, $col + 1);
    }


    public function seek($row)
    {
        # The odbc driver doesn't support seeking, so we fetch specific rows in getNextRow()
        return true;
    }


    public function count()
    {
        return odbc_num_rows($this->result);
    }


    public function columnCount()
    {
        return odbc_num_fields($this->result);
    }


    public function free()
    {
        return odbc_free_result($this->result);
    }
}
