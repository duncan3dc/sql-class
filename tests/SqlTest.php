<?php

class SqlTest extends PHPUnit_Framework_TestCase {

    private $sql;


    public function __construct() {

        $this->sql = new \duncan3dc\SqlClass\Sql([
            "mode"      =>  "sqlite",
            "database"  =>  "/tmp/phpunit.sqlite",
        ]);

        $this->sql->definitions(array(
            "table1"    =>  "test1",
            "table2"    =>  "test2",
        ));

    }


    public function __destruct() {

        $this->sql->mode = false;

    }


    protected function callProtectedMethod($methodName,&$params) {

        $class = new ReflectionClass("\\duncan3dc\\SqlClass\\Sql");

        $method = $class->getMethod($methodName);

        $method->setAccessible(true);

        if(is_array($params)) {
            return $method->invokeArgs($this->sql,$params);
        } elseif($params) {
            return $method->invokeArgs($this->sql,[&$params]);
        } else {
            return $method->invokeArgs($this->sql);
        }

    }


    public function testQuoteChars() {

        $query = "SELECT field1 `field2` FROM table1";

        $modes = array(
            "mssql"    =>  "SELECT field1 [field2] FROM table1",
            "mysql"    =>  "SELECT field1 `field2` FROM table1",
            "odbc"     =>  "SELECT field1 \"field2\" FROM table1",
            "sqlite"   =>  "SELECT field1 `field2` FROM table1",
        );
        foreach($modes as $mode => $check) {
            $this->sql->mode = $mode;
            $this->callProtectedMethod("quoteChars",$query);
            $this->assertEquals($check,$query);
        }

    }


    public function testQuoteCharsBackcheck() {

        $this->sql->mode = "mssql";

        $check = "SELECT a.field1, SUM(b.field2) [field2], SUM(c.field3) [field3], 'field4' [field4] FROM {table1} a
                JOIN {table2} b ON b.field1=a.field1 AND b.field5='10'
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1";

        $query = "SELECT a.field1, SUM(b.field2) `field2`, SUM(c.field3) `field3`, 'field4' `field4` FROM {table1} a
                JOIN {table2} b ON b.field1=a.field1 AND b.field5='10'
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1
                JOIN {table2} b ON b.field1=a.field1";
        $this->callProtectedMethod("quoteChars",$query);

        $this->assertEquals($check,$query);

    }


    public function testFunctions() {

        $query = "SELECT IFNULL(field1,0), SUBSTR(field1,5,3) FROM table1";

        $modes = array(
            "mssql"    =>  "SELECT ISNULL(field1,0), SUBSTRING(field1,5,3) FROM table1",
            "sqlite"   =>  "SELECT IFNULL(field1,0), SUBSTR(field1,5,3) FROM table1",
            "odbc"     =>  "SELECT IFNULL(field1,0), SUBSTRING(field1,5,3) FROM table1",
            "mysql"    =>  "SELECT IFNULL(field1,0), SUBSTRING(field1,5,3) FROM table1",
        );
        foreach($modes as $mode => $check) {
            $this->sql->mode = $mode;
            $this->callProtectedMethod("functions",$query);
            $this->assertEquals($check,$query);
        }


    }


    public function testLimit() {

        $modes = array(
            "mysql"    =>  ["SELECT * FROM table1 FETCH FIRST 10 ROWS ONLY", "SELECT * FROM table1 \nLIMIT 10\n"],
            "sqlite"   =>  ["SELECT * FROM table1
                        FETCH FIRST 10 ROWS ONLY", "SELECT * FROM table1
                        \nLIMIT 10\n"],
            "odbc"     =>  ["SELECT * FROM table1 LIMIT 1", "SELECT * FROM table1 \nFETCH FIRST 1 ROWS ONLY\n"],
        );
        foreach($modes as $mode => list($query,$check)) {
            $this->sql->mode = $mode;
            $this->callProtectedMethod("limit",$query);
            $this->assertEquals($check,$query);
        }


    }


    public function testTableNames() {

        $modes = array(
            "mysql"    =>  ["SELECT * FROM {table1}", "SELECT * FROM `test1`.`table1`"],
            "mssql"    =>  ["SELECT * FROM {table1}
                            LEFT OUTER JOIN hardcoded.nosuchtable ON field2a=field1a", "SELECT * FROM [test1].dbo.[table1]
                            LEFT OUTER JOIN hardcoded.nosuchtable ON field2a=field1a"],
            "odbc"     =>  ["SELECT * FROM {table1}", "SELECT * FROM test1.table1"],
            "sqlite"   =>  ["SELECT * FROM {table1}
                            LEFT OUTER JOIN {nosuchtable} ON field2a=field1a", "SELECT * FROM `test1`.`table1`
                            LEFT OUTER JOIN nosuchtable ON field2a=field1a"],
        );
        foreach($modes as $mode => list($query,$check)) {
            $this->sql->mode = $mode;
            $this->callProtectedMethod("tableNames",$query);
            $this->assertEquals($check,$query);
        }

    }


    public function testQueryParams() {

        $checkQuery = "SELECT * FROM test
            WHERE field1=?
                AND field2 IN ((?,?,?))
                AND field3=?
                AND field4<>?
                AND field5 NOT IN (?,?,?)
                AND field6=?
                AND field7=?
                AND field8 IN (?,?)
            ORDER BY field2";
        $checkParams = array(
            "marker1",
            "marker2a",
            "marker2b",
            "marker2c",
            "marker3",
            "marker4a",
            "marker5a",
            "marker5b",
            "marker5c",
            "marker6a",
            "marker7a",
            "marker8a",
            "marker8b",
        );

        $query = "SELECT * FROM test
            WHERE field1=?
                AND field2 IN (?)
                AND field3=?
                AND field4 NOT IN ?
                AND field5 NOT IN ?
                AND field6 IN ?
                AND field7 IN ?
                AND field8 IN ?
            ORDER BY field2";
        $params = array(
            "marker1",
            array("marker2a","marker2b","marker2c"),
            "marker3",
            array("marker4a"),
            array("marker5a","marker5b","marker5c"),
            array("marker6a"),
            array("assoc" => "marker7a"),
            array("assoc1" => "marker8a","assoc2" => "marker8b"),
        );

        $args = [&$query,&$params];
        $this->callProtectedMethod("paramArrays",$args);
        $this->assertEquals($checkQuery,$query);
        $this->assertEquals($checkParams,$params);

    }


    public function testPrepareQuery() {

        $this->sql->mode = "mssql";

        $query = "SELECT * FROM test WHERE field1=? ORDER BY field2";
        $params = array(
            "Test's",
        );

        $check = "SELECT * FROM test WHERE field1='Test''s' ORDER BY field2";

        $args = [&$query,&$params];
        $query = $this->callProtectedMethod("prepareQuery",$args);
        $this->assertEquals($check,$query);

    }


    public function testPrepareQueryTypes() {

        $this->sql->mode = "odbc";

        $check = "SELECT * FROM test
            WHERE field1='Test'
                AND field2<3.4
                AND field3='3.5.6'
                AND field4=''
                AND field5=0
                AND field6=''
                AND field7=0
                AND field8='00004'
            ORDER BY field2";

        $query = "SELECT * FROM test
            WHERE field1=?
                AND field2<?
                AND field3=?
                AND field4=?
                AND field5=?
                AND field6=?
                AND field7=?
                AND field8=?
            ORDER BY field2";
        $params = array(
            "Test",
            3.4,
            "3.5.6",
            null,
            false,
            "",
            0,
            "00004",
        );
        $args = [&$query,&$params];
        $query = $this->callProtectedMethod("prepareQuery",$args);

        $this->assertEquals($check,$query);

    }


    public function testPrepareQueryNulls() {

        $this->sql->mode = "odbc";
        $this->sql->allowNulls = true;

        $check = "SELECT * FROM test
            WHERE field1='Test'
                AND field2<3.4
                AND field3='3.5.6'
                AND field4=NULL
                AND field5=0
                AND field6=''
                AND field7=0
                AND field8='00004'
            ORDER BY field2";

        $query = "SELECT * FROM test
            WHERE field1=?
                AND field2<?
                AND field3=?
                AND field4=?
                AND field5=?
                AND field6=?
                AND field7=?
                AND field8=?
            ORDER BY field2";
        $params = array(
            "Test",
            3.4,
            "3.5.6",
            null,
            false,
            "",
            0,
            "00004",
        );
        $args = [&$query,&$params];
        $query = $this->callProtectedMethod("prepareQuery",$args);

        $this->assertEquals($check,$query);

    }


    public function testSelectFields() {

        $check = "''";
        $result = $this->sql->selectFields("");
        $this->assertEquals($check,$result);

        $check = "''";
        $result = $this->sql->selectFields(array());
        $this->assertEquals($check,$result);

        $this->sql->mode = "mysql";
        $check = "`field1`, `field2`";
        $result = $this->sql->selectFields(array("field1","field2"));
        $this->assertEquals($check,$result);

        $this->sql->mode = "mssql";
        $check = "[field1]";
        $result = $this->sql->selectFields(array("field1"));
        $this->assertEquals($check,$result);

        $check = "''";
        $result = $this->sql->selectFields(true);
        $this->assertEquals($check,$result);

        $check = "''";
        $result = $this->sql->selectFields(false);
        $this->assertEquals($check,$result);

        $check = "*";
        $result = $this->sql->selectFields("*");
        $this->assertEquals($check,$result);

        $check = "field1, field2, field3";
        $result = $this->sql->selectFields("field1, field2, field3");
        $this->assertEquals($check,$result);

    }


    public function testOrderBy() {

        $check = "ORDER BY `field1`";
        $query = $this->sql->orderBy("field1");
        $this->assertEquals($check,$query);

        $check = "ORDER BY `field1`, `field2`, `field3`";
        $query = $this->sql->orderBy("field1, field2, field3");
        $this->assertEquals($check,$query);

        $check = "";
        $query = $this->sql->orderBy("");
        $this->assertEquals($check,$query);

        $check = "";
        $query = $this->sql->orderBy(array());
        $this->assertEquals($check,$query);

        $check = "ORDER BY `field1`, `field2`, `field3`";
        $query = $this->sql->orderBy(array("field1","field2","field3"));
        $this->assertEquals($check,$query);

        $check = "ORDER BY CASE WHEN field1>0 THEN 0 ELSE 1 END, `field2`";
        $query = $this->sql->orderBy("CASE WHEN field1>0 THEN 0 ELSE 1 END, field2");
        $this->assertEquals($check,$query);

    }


    public function testModifyQuery() {

        $this->sql->mode = "mssql";

        $check = "ORDER BY 'basic `protection` against {special} [characters] withing strings', [field1]";
        $query = "ORDER BY 'basic `protection` against {special} [characters] withing strings', `field1`";

        $this->callProtectedMethod("quoteChars",$query);
        $this->assertEquals($check,$query);

        $this->callProtectedMethod("tableNames",$query);
        $this->assertEquals($check,$query);

    }


    public function namedParams() {

        $this->sql->mode = "mysql";

        $check = "SELECT field1 `field1`, field2 `field2`, field3 `field3` FROM table1 a
                JOIN table2 b ON b.field1=a.field1 AND b.field2='?' AND b.field3='three' AND b.field4='four'
                JOIN table2 b ON b.field1=a.field1 AND b.field2='test ? test {test2}' AND b.field3='three' AND b.field4='four'
                WHERE field1='one'
                        AND field2='two'
                        AND field3='three'
                        AND field4='four'";

        $query = "SELECT field1 `field1`, field2 [field2], field3 \"field3\" FROM {table1} a
                JOIN {table2} b ON b.field1=a.field1 AND b.field2='?' AND b.field3=?field3 AND b.field4=?field4
                JOIN {table2} b ON b.field1=a.field1 AND b.field2='test ? test {test2}' AND b.field3=?field3 AND b.field4=?field4
                WHERE field1=?field1
                    AND field2=?field2
                    AND field3=?field3
                    AND field4=?field4";
        $params = array(
            "field3"    =>  "three",
            "field4"    =>  "four",
            "field1"    =>  "one",
            "field2"    =>  "two",
        );

        $args = [&$query,&$params];
        $query = $this->callProtectedMethod("prepareQuery",$args);
        $this->assertEquals($check,$query);

    }


}
