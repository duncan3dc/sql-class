<?php

namespace duncan3dc\SqlClass\Where;

abstract class AbstractWhere
{
    protected $values;

    public function __construct(...$values)
    {
        $this->values = $values;
    }

    abstract public function getClause();

    public function getValue()
    {
        return $this->values[0];
    }

    public function getValues()
    {
        return $this->values;
    }
}
