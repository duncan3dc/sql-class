<?php

namespace duncan3dc\SqlClass\Where;

class NotIn extends In
{
    public function getClause()
    {
        if (count($this->values) < 2) {
            return "<> ?";
        }

        $markers = array_fill(0, count($this->values), "?");
        return "NOT IN (" . implode(", ", $markers) . ")";
    }
}
