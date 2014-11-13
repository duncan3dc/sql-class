<?php

namespace duncan3dc\SqlClass\Where;

/**
 * Generate a greater than or equal to than clause.
 */
class NotLessThan extends AbstractWhere
{
    /**
     * {@inheritDoc}
     */
    public function getClause()
    {
        return ">= ?";
    }
}
