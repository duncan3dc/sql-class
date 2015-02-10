<?php

namespace duncan3dc\SqlClass\Query;

/**
 * Generate select queries using methods.
 */
class Select extends Builder
{
    public function __construct()
    {
        parent::__construct(parent::TYPE_SELECT);
    }

    public function from($table)
    {
        return $this->table($table);
    }
}
