<?php

namespace duncan3dc\SqlClass\Engine\Mysql;

use duncan3dc\SqlClass\Engine\AbstractResult;

class Result extends AbstractResult
{

    public function getNextRow()
    {
        return db2_fetch_assoc($this->result);
    }


    public function result($row, $col)
    {
        db2_fetch_row($this->result, $row + 1);
        return db2_result($this->result, $col + 1);
    }


    public function seek($row)
    {
        db2_fetch_row($this->result, $row + 1);
    }


    public function count()
    {
        return db2_num_rows($this->result);
    }


    public function columnCount()
    {
        return db2_num_fields($this->result);
    }


    public function free()
    {
        return db2_free_result($this->result);
    }
}
