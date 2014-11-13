<?php

namespace duncan3dc\SqlClass\Where;

/**
 * Generate a equal to clause.
 */
class Equals extends AbstractWhere
{
    /**
     * {@inheritDoc}
     */
    public function getClause()
    {
        return "= ?";
    }
}
