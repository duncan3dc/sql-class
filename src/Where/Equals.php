<?php

namespace duncan3dc\SqlClass\Where;

class Equals extends AbstractWhere
{
    public function getClause()
    {
        return "= ?";
    }
}
