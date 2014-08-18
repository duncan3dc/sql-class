<?php

namespace duncan3dc\SqlClassTest;

use duncan3dc\SqlClass\Sql;

class ResultTest extends AbstractTest
{

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
}
