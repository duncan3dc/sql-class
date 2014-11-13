<?php

namespace duncan3dc\SqlClass\Where;

/**
 * Generate a like than clause.
 */
class Like extends AbstractWhere
{
    /**
     * {@inheritDoc}
     */
    public function getClause()
    {
        return "LIKE ?";
    }
}
