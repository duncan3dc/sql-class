<?php

namespace duncan3dc\SqlClass\Where;

/**
 * Generate a clause for use in a query.
 */
abstract class AbstractWhere
{
    /**
     * @var array $values The values to be used in the clause
     */
    protected $values;

    /**
     * Create an instance of the class for the passed values.
     *
     * @param mixed $values,... The values used by the clause
     */
    public function __construct(...$values)
    {
        $this->values = $values;
    }


    /**
     * Get the string version of the clause.
     *
     * @return string
     */
    abstract public function getClause();


    /**
     * Get the values to be used in the clause.
     *
     * @return mixed
     */
    public function getValues()
    {
        return $this->values;
    }
}
