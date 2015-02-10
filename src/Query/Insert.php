<?php

namespace duncan3dc\SqlClass\Query;

/**
 * Generate insert queries using methods.
 */
class Insert extends Builder
{
    public function __construct()
    {
        parent::__construct(parent::TYPE_INSERT);
    }

    public function into($table)
    {
        return $this->table($table);
    }
}
