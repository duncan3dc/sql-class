<?php

namespace duncan3dc\SqlClassTests;

use duncan3dc\SqlClass\Sql;
use PHPUnit\Framework\TestCase;

class IteratorTest extends TestCase
{
    private $sql;


    public function setUp(): void
    {
        $database = "/tmp/phpunit_" . microtime(true) . ".sqlite";
        if (file_exists($database)) {
            unlink($database);
        }

        $this->sql = new Sql([
            "mode"      =>  "sqlite",
            "database"  =>  "/tmp/phpunit.sqlite",
        ]);

        $this->sql->attachDatabase($database, "test1");

        $this->sql->definitions([
            "table1"    =>  "test1",
            "table2"    =>  "test1",
        ]);

        $this->sql->connect();

        $this->sql->query("CREATE TABLE test1.table1 (field1 VARCHAR(10), field2 INT)");
    }


    public function tearDown(): void
    {
        unset($this->sql);
    }


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
