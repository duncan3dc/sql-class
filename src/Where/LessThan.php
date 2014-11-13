<?php

namespace duncan3dc\SqlClass\Where;

/**
 * Generate a less than clause.
 */
class LessThan extends AbstractWhere
{
    /**
     * {@inheritDoc}
     */
    public function getClause()
    {
        return "< ?";
    }
}
