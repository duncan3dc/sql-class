<?php

namespace duncan3dc\SqlClass\Query;

/**
 * Generate delete queries using methods.
 */
class Delete extends Builder
{
    public function __construct()
    {
        parent::__construct(parent::TYPE_DELETE);
    }

    public function from($table)
    {
        return $this->table($table);
    }
}
