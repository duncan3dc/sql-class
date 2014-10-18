<?php

namespace duncan3dc\SqlClass\Where;

class LessThanOrEqualTo extends AbstractWhere
{
    public function getClause()
    {
        return "<= ?";
    }
}
