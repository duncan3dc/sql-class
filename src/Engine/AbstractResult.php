<?php

namespace duncan3dc\SqlClass\Engine;

use duncan3dc\SqlClass\Sql;

abstract class AbstractResult extends \duncan3dc\SqlClass\AbstractResult
{
    public function __construct($result)
    {
        $this->result = $result;
    }


    public function result($row, $col)
    {
        $position = $this->position;
        $this->seek($row);
        $data = $this->fetch(Sql::FETCH_ROW);
        $value = $data[$col];
        $this->seek($position);
        return $value;
    }


    abstract public function free();
}
