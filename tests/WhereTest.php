<?php

namespace duncan3dc\SqlClass;

class WhereTest extends SqlTest
{

    public function __construct()
    {
        parent::__construct();
        $this->sql->mode = "mysql";
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


    public function testEqualTo()
    {
        $this->checkQueryParams("`field1` = ? ", [
            "field1"    =>  Sql::equals("one"),
        ]);
    }


    public function testGreaterThan()
    {
        $this->checkQueryParams("`field1` > ? ", [
            "field1"    =>  Sql::greaterThan("one"),
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


    public function testEquals()
    {
        $this->checkQueryParams("`field1` = ? ", [
            "field1"    =>  Sql::equals("one%"),
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
}
