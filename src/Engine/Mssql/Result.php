<?php

namespace duncan3dc\SqlClass\Engine\Mssql;

use duncan3dc\SqlClass\Engine\AbstractResult;

class Result extends AbstractResult
{

    public function getNextRow()
    {
        return mssql_fetch_assoc($this->result);
    }


    public function result($row, $col)
    {
        return mssql_result($this->result, $row, $col);
    }


    public function seek($row)
    {
        return mssql_data_seek($this->result, $row);
    }


    public function count()
    {
        return mssql_num_rows($this->result);
    }


    public function columnCount()
    {
        return mssql_num_fields($this->result);
    }


    public function free()
    {
        return mssql_free_result($this->result);
    }
}
