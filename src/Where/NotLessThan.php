<?php

namespace duncan3dc\SqlClass\Where;

class NotLessThan extends AbstractWhere
{
    public function getClause()
    {
        return ">= ?";
    }
}
