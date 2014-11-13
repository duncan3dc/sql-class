<?php

namespace duncan3dc\SqlClass\Where;

class NotGreaterThan extends AbstractWhere
{
    /**
     * {@inheritDoc}
     */
    public function getClause()
    {
        return "<= ?";
    }
}
