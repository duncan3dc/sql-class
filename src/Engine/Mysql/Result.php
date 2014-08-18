<?php

namespace duncan3dc\SqlClass\Engine\Mysql;

use duncan3dc\SqlClass\Sql as SqlClass;

class Result extends \duncan3dc\SqlClass\AbstractResult
{

    public function getNextRow()
    {
        return $this->result->fetch_assoc();
    }


    public function seek($row)
    {
        return $this->result->data_seek($row);
    }


    public function count()
    {
        return $this->result->num_rows;
    }


    public function columnCount()
    {
        return $this->result->field_count;
    }


    public function free()
    {
        return$this->result->free();
    }
}
