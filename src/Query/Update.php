<?php

namespace duncan3dc\SqlClass\Query;

/**
 * Generate update queries using methods.
 */
class Update extends Builder
{
    public function __construct()
    {
        parent::__construct(parent::TYPE_UPDATE);
    }

    public function getQuery()
    {
        $query = "UPDATE " . $this->getTable() . " SET ";

        foreach ($set as $key => $val) {
            $query .= $this->quoteField($key) . "=?,";
        }

        $query = substr($query, 0, -1) . " ";

        $this->addWhereClause($query);

        return $query;
    }

    public function getParams()
    {
        $params = [];

        foreach ($this->set as $val) {
            $params[] = $val;
        }

        $query = "";
        $where = $this->addWhereClause($query);
        foreach ($where as $val) {
            $params[] = $val;
        }

        return $params;
    }
}
