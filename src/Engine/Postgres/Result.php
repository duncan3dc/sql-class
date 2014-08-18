<?php

namespace duncan3dc\SqlClass\Engine\Postgres;

use duncan3dc\SqlClass\Engine\AbstractResult;

class Result extends AbstractResult
{

    public function getNextRow()
    {
        return pg_fetch_assoc($this->result);
    }


    public function result($row, $col)
    {
        return pg_fetch_result($this->result, $row, $col);
    }


    public function seek($row)
    {
        pg_result_seek($this->result, $row);
    }


    public function count()
    {
        return pg_num_rows($this->result);
    }


    public function columnCount()
    {
        return $this->result->field_count;
    }


    public function free()
    {
        return pg_free_result($this->result);
    }
}
