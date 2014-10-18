<?php

namespace duncan3dc\SqlClass\Where;

class GreaterThanOrEqualTo extends AbstractWhere
{
    public function getClause()
    {
        return ">= ?";
    }
}
