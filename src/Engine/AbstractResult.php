<?php

namespace duncan3dc\SqlClass\Engine;

use duncan3dc\SqlClass\Sql;

abstract class AbstractResult implements ResultInterface
{
    public function __construct($result)
    {
        $this->result = $result;
    }


    abstract public function free();
}
