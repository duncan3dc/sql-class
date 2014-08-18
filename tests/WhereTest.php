<?php

namespace duncan3dc\SqlClassTests;

use duncan3dc\SqlClass\Sql;

class WhereTest extends AbstractTest
{

    public function setUp()
    {
        parent::setUp();
        $this->setMode("mysql");
    }


    public function checkQueryParams($check, $where)
    {
        $params = [];
        $args = [&$where, &$params];
        $query = $this->callProtectedMethod("where", $args);
        $this->assertSame($check, $query);
    }


    public function testPlain()
    {
        $this->checkQueryParams("`field1` = ? AND `field2` = ? ", [
            "field1"    =>  "one",
            "field2"    =>  "two",
        ]);
    }


    public function testEquals()
    {
        $this->checkQueryParams("`field1` = ? ", [
            "field1"    =>  Sql::equals("one"),
        ]);
    }


    public function testEqualTo()
    {
        $this->checkQueryParams("`field1` = ? ", [
            "field1"    =>  Sql::equalTo("one"),
        ]);
    }


    public function testGreaterThan()
    {
        $this->checkQueryParams("`field1` > ? ", [
            "field1"    =>  Sql::greaterThan("one"),
        ]);
    }


    public function testNotGreaterThan()
    {
        $this->checkQueryParams("`field1` <= ? ", [
            "field1"    =>  Sql::notGreaterThan("one"),
        ]);
    }


    public function testGreaterThanOrEqualTo()
    {
        $this->checkQueryParams("`field1` >= ? ", [
            "field1"    =>  Sql::greaterThanOrEqualTo("one"),
        ]);
    }


    public function testLessThan()
    {
        $this->checkQueryParams("`field1` < ? ", [
            "field1"    =>  Sql::lessThan("one"),
        ]);
    }


    public function testNotLessThan()
    {
        $this->checkQueryParams("`field1` >= ? ", [
            "field1"    =>  Sql::notLessThan("one"),
        ]);
    }


    public function testLessThanOrEqualTo()
    {
        $this->checkQueryParams("`field1` <= ? ", [
            "field1"    =>  Sql::lessThanOrEqualTo("one"),
        ]);
    }


    public function testLike()
    {
        $this->checkQueryParams("`field1` LIKE ? ", [
            "field1"    =>  Sql::like("one%"),
        ]);
    }


    public function testNotLike()
    {
        $this->checkQueryParams("`field1` NOT LIKE ? ", [
            "field1"    =>  Sql::notLike("one%"),
        ]);
    }


    public function testNotEqualTo()
    {
        $this->checkQueryParams("`field1` <> ? ", [
            "field1"    =>  Sql::notEqualTo("one%"),
        ]);
    }


    public function testIn1()
    {
        $this->checkQueryParams("`field1` IN (?, ?) ", [
            "field1"    =>  Sql::in("one", "two"),
        ]);
    }
    public function testIn2()
    {
        $this->checkQueryParams("`field1` IN (?, ?) ", [
            "field1"    =>  Sql::in(["one", "two"]),
        ]);
    }
    public function testIn3()
    {
        $this->checkQueryParams("`field1` = ? ", [
            "field1"    =>  Sql::in("one"),
        ]);
    }


    public function testIn4()
    {
        $this->checkQueryParams("`field1` = ? ", [
            "field1"    =>  Sql::in(["one"]),
        ]);
    }
    public function testNotIn1()
    {
        $this->checkQueryParams("`field1` NOT IN (?, ?) ", [
            "field1"    =>  Sql::notIn("one", "two"),
        ]);
    }
    public function testNotIn2()
    {
        $this->checkQueryParams("`field1` NOT IN (?, ?) ", [
            "field1"    =>  Sql::notIn(["one", "two"]),
        ]);
    }
    public function testNotIn3()
    {
        $this->checkQueryParams("`field1` <> ? ", [
            "field1"    =>  Sql::notIn("one"),
        ]);
    }
    public function testNotIn4()
    {
        $this->checkQueryParams("`field1` <> ? ", [
            "field1"    =>  Sql::notIn(["one"]),
        ]);
    }


    public function testBetween()
    {
        $this->checkQueryParams("`field1` BETWEEN ? AND ? ", [
            "field1"    =>  Sql::between("one", "two"),
        ]);
    }
}
