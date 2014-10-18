<?php

namespace duncan3dc\SqlClass\Where;

class Like extends AbstractWhere
{
    public function getClause()
    {
        return "LIKE ?";
    }
}
