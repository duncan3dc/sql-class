<?php

namespace duncan3dc\SqlClass;

/**
 * Allow multiple rows to be inserted much more efficiently.
 */
class BulkInsert
{
    /**
     * @var Sql $sql The Sql instance to insert the records via.
     */
    protected $sql;

    /**
     * @var string $table The name of the table to insert into.
     */
    protected $table;

    /**
     * @var int $limit The maximum number of rows to insert at a time.
     */
    protected $limit;

    /**
     * @var string[] $fields The fields to use in the insert query.
     */
    protected $fields = [];

    /**
     * @var array $rows The records to insert.
     */
    protected $rows = [];


    /**
     * Create a new instance.
     *
     * @param Sql $sql The Sql instance to insert the records via
     * @param string $table The name of the table to insert into
     * @param int $limit The maximum number of rows to insert at a time
     */
    public function __construct(Sql $sql, $table, $limit)
    {
        $this->sql = $sql;
        $this->table = (string) $table;
        $this->limit = (int) $limit;
    }


    /**
     * Queue a record to be inserted.
     *
     * @param array $row An associative array of field names and their values to insert
     *
     * @return void
     */
    public function insert(array $row)
    {
        if (count($this->fields) < 1) {
            $this->fields = array_keys($row);
        } else {
            $fields = array_diff(array_keys($row), $this->fields);
            $this->fields = array_merge($this->fields, $fields);
            $extra = array_fill_keys($fields, null);
            $this->rows = array_map(function($row) use($extra) {
                return array_merge($row, $extra);
            }, $this->rows);
        }

        $values = [];
        foreach ($this->fields as $field) {
            $values[$field] = isset($row[$field]) ? $row[$field] : null;
        }

        $this->rows[] = $values;

        $this->bulkInsert($this->limit);
    }


    /**
     * Insert the records if we have reached the specified limit.
     *
     * @param int $limit The maximum number of rows to insert at a time
     *
     * @return void
     */
    protected function bulkInsert($limit)
    {
        if (count($this->rows) < $limit) {
            return;
        }

        $this->sql->bulkInsert($this->table, $this->rows);

        $this->rows = [];
        $this->fields = [];
    }


    /**
     * Ensure any outstanding records are inserted.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->bulkInsert(1);
    }
}
