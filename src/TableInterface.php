<?php

namespace duncan3dc\SqlClass;

interface TableInterface
{

    public function batchInsert($limit);


    public function cache();


    public function cacheNext();


    public function cacheAll();


    public function update(array $set, array $where);


    public function insert(array $params, $extra = null);


    public function bulkInsert(array $params, $extra = null);


    public function delete(array $where);


    public function truncate();

    /**
     * Grab the first row from a table using the standard select statement
     * This is a convience method for a fieldSelect() where all fields are required
     */
    public function select(array $where, $orderBy = null);


    /**
     * Grab specific fields from the first row from a table using the standard select statement
     */
    public function fieldSelect($fields, array $where, $orderBy = null);


    /**
     * Create a standard select statement and return the result
     * This is a convience method for a fieldSelectAll() where all fields are required
     */
    public function selectAll(array $where, $orderBy = null);


    /**
     * Create a standard select statement and return the result
     */
    public function fieldSelectAll($fields, array $where, $orderBy = null);


    /**
     * Check if a record exists without fetching any data from it.
     *
     * @param array|int $where The where clause to use, or the NO_WHERE_CLAUSE constant
     *
     * @return boolean Whether a matching row exists in the table or not
     */
    public function exists(array $where);


    /**
     * Insert a new record into a table, unless it already exists in which case update it
     */
    public function insertOrUpdate(array $set, array $where);
}
