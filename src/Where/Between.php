<?php

namespace duncan3dc\SqlClass\Where;

class Between extends AbstractWhere
{
    public function getClause()
    {
        return "BETWEEN ? AND ?";
    }
}
