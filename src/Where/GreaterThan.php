<?php

namespace duncan3dc\SqlClass\Where;

class GreaterThan extends AbstractWhere
{
    /**
     * {@inheritDoc}
     */
    public function getClause()
    {
        return "> ?";
    }
}
