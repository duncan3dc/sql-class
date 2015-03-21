<?php

namespace duncan3dc\SqlClass\Where;

class NotLike extends AbstractWhere
{
    public function getClause()
    {
        return "NOT LIKE ?";
    }
}
