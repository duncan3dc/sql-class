<?php

namespace duncan3dc\SqlClass\Where;

/**
 * Generate a not equal to than clause.
 */
class NotEqualTo extends AbstractWhere
{
    /**
     * {@inheritDoc}
     */
    public function getClause()
    {
        return "<> ?";
    }
}
