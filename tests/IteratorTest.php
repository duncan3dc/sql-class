<?php

namespace duncan3dc\SqlClassTests;

use duncan3dc\SqlClass\Sql;

class IteratorTest extends AbstractTest
{

    public function testWithOneRow()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);

        $counter = 0;
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        foreach ($result as $null) {
            $counter++;
        }
        $this->assertSame(1, $counter);
    }


    public function testWithTwoRows()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);
        $this->sql->insert("table1", ["field1" => "row2"]);

        $counter = 0;
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        foreach ($result as $null) {
            $counter++;
        }
        $this->assertSame(2, $counter);
    }


    public function testWithZeroRows()
    {
        $counter = 0;
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        foreach ($result as $null) {
            $counter++;
        }
        $this->assertSame(0, $counter);
    }


    public function testMultipleLoopsWithOneRow()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);

        $counter = 0;
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        foreach ($result as $null) {
        }
        foreach ($result as $null) {
            $counter++;
        }
        $this->assertSame(1, $counter);
    }


    public function testMultipleLoopsWithTwoRows()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);
        $this->sql->insert("table1", ["field1" => "row2"]);

        $counter = 0;
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        foreach ($result as $null) {
        }
        foreach ($result as $null) {
            $counter++;
        }
        $this->assertSame(2, $counter);
    }


    public function testMultipleLoopsWithZeroRows()
    {
        $counter = 0;
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        foreach ($result as $null) {
        }
        foreach ($result as $null) {
            $counter++;
        }
        $this->assertSame(0, $counter);
    }


    public function testGenerator1()
    {
        error_reporting(E_ALL ^ E_USER_DEPRECATED);

        $this->sql->insert("table1", ["field1" => "row1"]);
        $this->sql->insert("table1", ["field1" => "row2"]);

        $counter = 0;
        $rows = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE)->fetch(Sql::FETCH_GENERATOR);
        foreach ($rows as $null) {
            $counter++;
        }
        $this->assertSame(2, $counter);
    }

    public function testGenerator2()
    {
        error_reporting(E_ALL ^ E_USER_DEPRECATED);

        $this->sql->insert("table1", ["field1" => "row1"]);

        $rows = $this->sql->fieldSelectAll("table1", "field1", Sql::NO_WHERE_CLAUSE)->fetch(Sql::FETCH_GENERATOR);
        foreach ($rows as $val) {
            $this->assertSame("row1", $val);
        }
    }

    public function testGenerator3()
    {
        error_reporting(E_ALL ^ E_USER_DEPRECATED);

        $this->sql->insert("table1", ["field1" => "key", "field2"  =>  "val"]);

        $rows = $this->sql->fieldSelectAll("table1", ["field1", "field2"], Sql::NO_WHERE_CLAUSE)->fetch(Sql::FETCH_GENERATOR);
        foreach ($rows as $key => $val) {
            $this->assertSame("key", $key);
            $this->assertSame("val", $val);
        }
    }
}
