<?php

namespace duncan3dc\SqlClass\Where;

class LessThan extends AbstractWhere
{
    public function getClause()
    {
        return "< ?";
    }
}
