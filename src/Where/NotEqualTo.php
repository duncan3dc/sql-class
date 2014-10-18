<?php

namespace duncan3dc\SqlClass\Where;

class NotEqualTo extends AbstractWhere
{
    public function getClause()
    {
        return "<> ?";
    }
}
