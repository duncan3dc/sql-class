<?php

namespace duncan3dc\SqlClass;

use Mockery;

class BulkInsertTest extends \PHPUnit_Framework_TestCase
{
    protected $table;

    public function setUp()
    {
        $this->table = Mockery::mock(Table::class);
        $this->table->shouldReceive("disconnect");
    }


    public function testDestructor()
    {
        $this->table->shouldReceive("bulkInsert")->once()->with([[
            "field1"    =>  "value1",
            "field2"    =>  "value2",
        ]]);

        $table1 = new BulkInsert($this->table, 10);

        $table1->insert([
            "field1"    =>  "value1",
            "field2"    =>  "value2",
        ]);
    }


    public function testLimit()
    {
        $this->table->shouldReceive("bulkInsert")->once()->with([
            ["field1" => "value1a"],
            ["field1" => "value1b"],
        ]);
        $this->table->shouldReceive("bulkInsert")->once()->with([
            ["field1" => "value1c"],
            ["field1" => "value1d"],
        ]);

        $table1 = new BulkInsert($this->table, 2);

        $table1->insert(["field1" => "value1a"]);
        $table1->insert(["field1" => "value1b"]);
        $table1->insert(["field1" => "value1c"]);
        $table1->insert(["field1" => "value1d"]);
    }


    public function testExtraFields()
    {
        $this->table->shouldReceive("bulkInsert")->once()->with([
            ["field1" => "value1a", "field2" => null],
            ["field1" => "value1b", "field2" => "value2b"],
        ]);

        $table1 = new BulkInsert($this->table, 10);

        $table1->insert(["field1" => "value1a"]);
        $table1->insert(["field1" => "value1b", "field2" => "value2b"]);
    }


    public function testExtraFieldsOutOfSequence()
    {
        $this->table->shouldReceive("bulkInsert")->once()->with([
            ["field1" => "value1a", "field2" => null, "field3" => null],
            ["field1" => "value1b", "field2" => "value2b", "field3" => null],
            ["field1" => null, "field2" => null, "field3" => "value3c"],
        ]);

        $table1 = new BulkInsert($this->table, 10);

        $table1->insert(["field1" => "value1a"]);
        $table1->insert(["field2" => "value2b", "field1" => "value1b"]);
        $table1->insert(["field3" => "value3c"]);
    }
}
