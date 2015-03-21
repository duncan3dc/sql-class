<?php

namespace duncan3dc\SqlClass\Where;

class NotGreaterThan extends AbstractWhere
{
    public function getClause()
    {
        return "<= ?";
    }
}
