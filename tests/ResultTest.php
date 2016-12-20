<?php

namespace duncan3dc\SqlClassTests;

use duncan3dc\SqlClass\Sql;

class ResultTest extends \PHPUnit_Framework_TestCase
{
    protected $sql;


    public function __construct()
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


    public function __destruct()
    {
        unset($this->sql);
    }


    public function testCount()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);
        $this->sql->insert("table1", ["field1" => "row2"]);

        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        $this->assertSame(2, $result->count());
    }


    public function testColumnCount()
    {
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        $this->assertSame(2, $result->columnCount());
    }


    public function testFetchAssoc1()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        $this->assertSame("row1", $result->fetch()["field1"]);
    }

    public function testFetchAssoc2()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        $this->assertSame("row1", $result->fetch(Sql::FETCH_ASSOC)["field1"]);
    }

    public function testFetchAssoc3()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        $result->fetchStyle(Sql::FETCH_ASSOC);
        $this->assertSame("row1", $result->fetch()["field1"]);
    }


    public function testFetchRow1()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        $this->assertSame("row1", $result->fetch(true)[0]);
    }

    public function testFetchRow2()
    {
        $this->sql->insert("table1", ["field1" => "row1", "field2" => "ok "]);
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        $this->assertSame("ok", $result->fetch(1)[1]);
    }

    public function testFetchRow3()
    {
        $this->sql->insert("table1", ["field1" => "row1", "field2" => "ok"]);
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        $this->assertSame("ok", $result->fetch(Sql::FETCH_ROW)[1]);
    }

    public function testFetchRow4()
    {
        $this->sql->insert("table1", ["field1" => "row1", "field2" => "ok"]);
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        $result->fetchStyle(Sql::FETCH_ROW);
        $this->assertSame("ok", $result->fetch()[1]);
    }


    public function testFetchRaw()
    {
        $this->sql->insert("table1", ["field1" => "row1 "]);
        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        $this->assertSame("row1 ", $result->fetch(Sql::FETCH_RAW)["field1"]);
    }


    public function testGetValues1()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);
        $this->sql->insert("table1", ["field1" => "row2"]);

        $counter = 0;
        $rows = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE)->getValues();
        foreach ($rows as $null) {
            $counter++;
        }
        $this->assertSame(2, $counter);
    }

    public function testGetValues2()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);

        $rows = $this->sql->fieldSelectAll("table1", "field1", Sql::NO_WHERE_CLAUSE)->getValues();
        foreach ($rows as $val) {
            $this->assertSame("row1", $val);
        }
    }

    public function testGetValues3()
    {
        $this->sql->insert("table1", ["field1" => "key", "field2"  =>  "val"]);

        $rows = $this->sql->fieldSelectAll("table1", ["field1", "field2"], Sql::NO_WHERE_CLAUSE)->getValues();
        foreach ($rows as $key => $val) {
            $this->assertSame("key", $key);
            $this->assertSame("val", $val);
        }
    }


    public function testGroupBy1()
    {
        $this->sql->bulkInsert("table1", [
            [
                "field1"    =>  "key1",
                "field2"    =>  "group1",
            ],
            [
                "field1"    =>  "key2",
                "field2"    =>  "group2",
            ],
            [
                "field1"    =>  "key3",
                "field2"    =>  "group1",
            ],
            [
                "field1"    =>  "key4",
                "field2"    =>  "group2",
            ],
        ]);

        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        $data = $result->groupBy("field2");

        $this->assertSame([
            "group1" => [
                [
                    "field1"    =>  "key1",
                    "field2"    =>  "group1",
                ],
                [
                    "field1"    =>  "key3",
                    "field2"    =>  "group1",
                ],
            ],
            "group2"    =>  [
                [
                    "field1"    =>  "key2",
                    "field2"    =>  "group2",
                ],
                [
                    "field1"    =>  "key4",
                    "field2"    =>  "group2",
                ],
            ],
        ], $data);
    }
    public function testGroupBy2()
    {
        $this->sql->bulkInsert("table1", [
            [
                "field1"    =>  "key1",
                "field2"    =>  "group1",
            ],
            [
                "field1"    =>  "key1",
                "field2"    =>  "group1",
            ],
            [
                "field1"    =>  "key2",
                "field2"    =>  "group1",
            ],
            [
                "field1"    =>  "key1",
                "field2"    =>  "group2",
            ],
        ]);

        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        $data = $result->groupBy("field1", "field2");

        $this->assertSame([
            "key1"  =>  [
                "group1"    =>  [
                    [
                        "field1"    =>  "key1",
                        "field2"    =>  "group1",
                    ],
                    [
                        "field1"    =>  "key1",
                        "field2"    =>  "group1",
                    ],
                ],
                "group2"    =>  [
                    [
                        "field1"    =>  "key1",
                        "field2"    =>  "group2",
                    ],
                ],
            ],
            "key2"  =>  [
                "group1"    =>  [
                    [
                        "field1"    =>  "key2",
                        "field2"    =>  "group1",
                    ],
                ],
            ],
        ], $data);
    }
    public function testGroupBy3()
    {
        $this->sql->insert("table1", [
            "field1"    =>  "key",
            "field2"    =>  "value",
        ]);

        $result = $this->sql->selectAll("table1", Sql::NO_WHERE_CLAUSE);
        $data = $result->groupBy(function (array $row) {
            return "group";
        });

        $this->assertSame([
            "group" => [
                [
                    "field1"    =>  "key",
                    "field2"    =>  "value",
                ],
            ],
        ], $data);
    }
}
