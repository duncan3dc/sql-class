<?php

namespace duncan3dc\SqlClass\Where;

class In extends AbstractWhere
{
    public function __construct(...$values)
    {
        if (count($values) === 1 && is_array($values[0])) {
            $this->values = $values[0];
        } else {
            $this->values = $values;
        }
    }

    public function getClause()
    {
        if (count($this->values) < 2) {
            return "= ?";
        }

        $markers = array_fill(0, count($this->values), "?");
        return "IN (" . implode(", ", $markers) . ")";
    }
}
