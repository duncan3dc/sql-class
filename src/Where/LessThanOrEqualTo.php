<?php

namespace duncan3dc\SqlClass\Where;

class LessThanOrEqualTo extends AbstractWhere
{
    /**
     * {@inheritDoc}
     */
    public function getClause()
    {
        return "<= ?";
    }
}
