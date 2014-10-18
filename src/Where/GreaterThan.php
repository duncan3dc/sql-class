<?php

namespace duncan3dc\SqlClass\Where;

class GreaterThan extends AbstractWhere
{
    public function getClause()
    {
        return "> ?";
    }
}
