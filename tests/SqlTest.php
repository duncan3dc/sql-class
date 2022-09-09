<?php

namespace duncan3dc\SqlClassTests;

use duncan3dc\ObjectIntruder\Intruder;
use duncan3dc\SqlClass\Sql;
use Mockery;
use PHPUnit\Framework\TestCase;

class SqlTest extends TestCase
{
    protected $sql;


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
            "table2"    =>  "test2",
        ]);

        $this->sql->connect();

        $this->sql->query("CREATE TABLE test1.table1 (field1 VARCHAR(10), field2 INT)");
    }


    public function tearDown(): void
    {
        $this->sql->mode = false;
    }


    protected function callProtectedMethod($methodName, &$params)
    {
        $class = new \ReflectionClass(Sql::class);

        $method = $class->getMethod($methodName);

        $method->setAccessible(true);

        if (is_array($params)) {
            return $method->invokeArgs($this->sql, $params);
        } elseif ($params) {
            return $method->invokeArgs($this->sql, [&$params]);
        } else {
            return $method->invokeArgs($this->sql);
        }
    }


    public function testQuoteChars()
    {
        $query = "SELECT field1 `field2` FROM table1";

        $modes = [
            "mssql"    =>  "SELECT field1 [field2] FROM table1",
            "mysql"    =>  "SELECT field1 `field2` FROM table1",
            "odbc"     =>  "SELECT field1 \"field2\" FROM table1",
            "sqlite"   =>  "SELECT field1 `field2` FROM table1",
        ];
        foreach ($modes as $mode => $check) {
            $this->sql->mode = $mode;
            $this->callProtectedMethod("quoteChars", $query);
            $this->assertEquals($check, $query);
        }
    }


    public function testQuoteCharsBackcheck()
    {
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
        $this->callProtectedMethod("quoteChars", $query);

        $this->assertEquals($check, $query);
    }


    public function testQuoteTables1()
    {
        $table = "table-with_extra+characters=in";

        $modes = [
            "mssql"    =>  "[table-with_extra+characters=in]",
            "mysql"    =>  "`table-with_extra+characters=in`",
            "odbc"     =>  "table-with_extra+characters=in",
            "sqlite"   =>  "`table-with_extra+characters=in`",
        ];
        foreach ($modes as $mode => $check) {
            $this->sql->mode = $mode;
            $result = $this->callProtectedMethod("getTableName", $table);
            $this->assertEquals($check, $result);
        }
    }
    public function testQuoteTables2()
    {
        $table = "database.table";

        $modes = ["mssql", "mysql", "odbc", "sqlite"];
        foreach ($modes as $mode) {
            $this->sql->mode = $mode;
            $result = $this->callProtectedMethod("getTableName", $table);
            $this->assertEquals($table, $result);
        }
    }


    public function testFunctions()
    {
        $query = "SELECT IFNULL(field1,0), SUBSTR(field1,5,3) FROM table1";

        $modes = [
            "mssql"    =>  "SELECT ISNULL(field1,0), SUBSTRING(field1,5,3) FROM table1",
            "sqlite"   =>  "SELECT IFNULL(field1,0), SUBSTR(field1,5,3) FROM table1",
            "odbc"     =>  "SELECT IFNULL(field1,0), SUBSTRING(field1,5,3) FROM table1",
            "mysql"    =>  "SELECT IFNULL(field1,0), SUBSTRING(field1,5,3) FROM table1",
        ];
        foreach ($modes as $mode => $check) {
            $this->sql->mode = $mode;
            $this->callProtectedMethod("functions", $query);
            $this->assertEquals($check, $query);
        }
    }


    public function testLimit()
    {
        $modes = [
            "mysql"    =>  ["SELECT * FROM table1 FETCH FIRST 10 ROWS ONLY", "SELECT * FROM table1 \nLIMIT 10\n"],
            "sqlite"   =>  ["SELECT * FROM table1
                        FETCH FIRST 10 ROWS ONLY", "SELECT * FROM table1
                        \nLIMIT 10\n"],
            "odbc"     =>  ["SELECT * FROM table1 LIMIT 1", "SELECT * FROM table1 \nFETCH FIRST 1 ROWS ONLY\n"],
        ];
        foreach ($modes as $mode => list($query, $check)) {
            $this->sql->mode = $mode;
            $this->callProtectedMethod("limit", $query);
            $this->assertEquals($check, $query);
        }
    }


    public function testTableNames()
    {
        $modes = [
            "mysql"    =>  ["SELECT * FROM {table1}", "SELECT * FROM `test1`.`table1`"],
            "mssql"    =>  ["SELECT * FROM {table1}
                            LEFT OUTER JOIN hardcoded.nosuchtable ON field2a=field1a", "SELECT * FROM [test1].dbo.[table1]
                            LEFT OUTER JOIN hardcoded.nosuchtable ON field2a=field1a"],
            "odbc"     =>  ["SELECT * FROM {table1}", "SELECT * FROM test1.table1"],
            "sqlite"   =>  ["SELECT * FROM {table1}
                            LEFT OUTER JOIN {nosuchtable} ON field2a=field1a", "SELECT * FROM `test1`.`table1`
                            LEFT OUTER JOIN `nosuchtable` ON field2a=field1a"],
        ];
        foreach ($modes as $mode => list($query, $check)) {
            $this->sql->mode = $mode;
            $this->callProtectedMethod("tableNames", $query);
            $this->assertEquals($check, $query);
        }
    }


    public function testQueryParams1()
    {
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
        $checkParams = [
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
        ];

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
        $params = [
            "marker1",
            ["marker2a", "marker2b", "marker2c"],
            "marker3",
            ["marker4a"],
            ["marker5a", "marker5b", "marker5c"],
            ["marker6a"],
            ["assoc" => "marker7a"],
            ["assoc1" => "marker8a", "assoc2" => "marker8b"],
        ];

        $args = [&$query, &$params];
        $this->callProtectedMethod("paramArrays", $args);
        $this->assertEquals($checkQuery, $query);
        $this->assertEquals($checkParams, $params);
    }
    public function testQueryParams2()
    {
        $checkQuery = "SELECT * FROM table
                    WHERE field1 REGEXP '^-?[0-9]+$' <> 1
                        AND field2 BETWEEN ? AND ?
                        AND field3 NOT IN (?,?)
                        AND field4 = ?";
        $checkParams = [
            100,
            200,
            "4",
            "9",
            "ok",
        ];

        $query = "SELECT * FROM table
                    WHERE field1 REGEXP '^-?[0-9]+$' <> 1
                        AND field2 BETWEEN ? AND ?
                        AND field3 NOT IN ?
                        AND field4 = ?";
        $params = [
            100,
            200,
            ["4", "9"],
            "ok",
        ];

        $args = [&$query, &$params];
        $this->callProtectedMethod("paramArrays", $args);
        $this->assertEquals($checkQuery, $query);
        $this->assertEquals($checkParams, $params);
    }
    public function testQueryParams3()
    {
        $checkQuery = "INSERT INTO table
                    (field1, field2, field3, fiedl4)
                    VALUES (?,?,?,?)";
        $checkParams = [
            "value1",
            "value2",
            "",
            "value4",
        ];

        $query = $checkQuery;
        $params = $checkParams;

        $args = [&$query, &$params];
        $this->callProtectedMethod("paramArrays", $args);
        $this->assertEquals($checkQuery, $query);
        $this->assertEquals($checkParams, $params);
    }
    public function testQueryParams4()
    {
        $checkQuery = "SELECT * FROM table
                        WHERE field1=?";
        $checkParams = [
            false,
        ];

        $query = "SELECT * FROM table
                        WHERE field1 IN ?";
        $params = [
            [],
        ];

        $args = [&$query, &$params];
        $this->callProtectedMethod("paramArrays", $args);
        $this->assertSame($checkQuery, $query);
        $this->assertSame($checkParams, $params);
    }


    public function testPrepareQuery()
    {
        $this->sql->mode = "mssql";

        $query = "SELECT * FROM test WHERE field1=? ORDER BY field2";
        $params = [
            "Test's",
        ];

        $check = "SELECT * FROM test WHERE field1='Test''s' ORDER BY field2";

        $args = [&$query, &$params];
        $query = $this->callProtectedMethod("prepareQuery", $args);
        $this->assertEquals($check, $query);
    }


    public function testPrepareQueryTypes()
    {
        $this->sql->mode = "odbc";
        $this->sql->allowNulls = false;

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
        $params = [
            "Test",
            3.4,
            "3.5.6",
            null,
            false,
            "",
            0,
            "00004",
        ];

        $args = [&$params];
        $this->callProtectedMethod("convertNulls", $args);

        $args = [&$query, &$params];
        $query = $this->callProtectedMethod("prepareQuery", $args);

        $this->assertEquals($check, $query);
    }


    public function testPrepareQueryNulls()
    {
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
        $params = [
            "Test",
            3.4,
            "3.5.6",
            null,
            false,
            "",
            0,
            "00004",
        ];

        $args = [&$params];
        $this->callProtectedMethod("convertNulls", $args);

        $args = [&$query, &$params];
        $query = $this->callProtectedMethod("prepareQuery", $args);

        $this->assertEquals($check, $query);
    }


    public function testSelectFields()
    {
        $check = "''";
        $result = $this->sql->selectFields("");
        $this->assertEquals($check, $result);

        $check = "''";
        $result = $this->sql->selectFields([]);
        $this->assertEquals($check, $result);

        $this->sql->mode = "mysql";
        $check = "`field1`, `field2`";
        $result = $this->sql->selectFields(["field1", "field2"]);
        $this->assertEquals($check, $result);

        $this->sql->mode = "mssql";
        $check = "[field1]";
        $result = $this->sql->selectFields(["field1"]);
        $this->assertEquals($check, $result);

        $check = "''";
        $result = $this->sql->selectFields(true);
        $this->assertEquals($check, $result);

        $check = "''";
        $result = $this->sql->selectFields(false);
        $this->assertEquals($check, $result);

        $check = "*";
        $result = $this->sql->selectFields("*");
        $this->assertEquals($check, $result);

        $check = "field1, field2, field3";
        $result = $this->sql->selectFields("field1, field2, field3");
        $this->assertEquals($check, $result);
    }


    public function testOrderBy()
    {
        $check = "ORDER BY `field1`";
        $query = $this->sql->orderBy("field1");
        $this->assertEquals($check, $query);

        $check = "ORDER BY `field1`, `field2`, `field3`";
        $query = $this->sql->orderBy("field1, field2, field3");
        $this->assertEquals($check, $query);

        $check = "";
        $query = $this->sql->orderBy("");
        $this->assertEquals($check, $query);

        $check = "";
        $query = $this->sql->orderBy([]);
        $this->assertEquals($check, $query);

        $check = "ORDER BY `field1`, `field2`, `field3`";
        $query = $this->sql->orderBy(["field1", "field2", "field3"]);
        $this->assertEquals($check, $query);

        $check = "ORDER BY CASE WHEN field1>0 THEN 0 ELSE 1 END, `field2`";
        $query = $this->sql->orderBy("CASE WHEN field1>0 THEN 0 ELSE 1 END, field2");
        $this->assertEquals($check, $query);
    }


    public function testModifyQuery()
    {
        $this->sql->mode = "mssql";

        $check = "ORDER BY 'basic `protection` against {special} [characters] withing strings', [field1]";
        $query = "ORDER BY 'basic `protection` against {special} [characters] withing strings', `field1`";

        $this->callProtectedMethod("quoteChars", $query);
        $this->assertEquals($check, $query);

        $this->callProtectedMethod("tableNames", $query);
        $this->assertEquals($check, $query);
    }


    public function testNamedParams()
    {
        $this->sql->mode = "sqlite";

        $check = "SELECT field1 FROM table1 a
                JOIN table2 b ON b.field1=a.field1 AND b.field2='?' AND b.field3='three' AND b.field4='four'
                JOIN table2 b ON b.field1=a.field1 AND b.field2='test ? test {test2}' AND b.field3='three' AND b.field4='four'
                WHERE field1='one'
                    AND field2='two'
                    AND field3='three'
                    AND field4='four'";

        $query = "SELECT field1 FROM table1 a
                JOIN table2 b ON b.field1=a.field1 AND b.field2='?' AND b.field3=?field3 AND b.field4=?field_4
                JOIN table2 b ON b.field1=a.field1 AND b.field2='test ? test {test2}' AND b.field3=?field3 AND b.field4=?field_4
                WHERE field1=?field1
                    AND field2=?field2
                    AND field3=?field3
                    AND field4=?field_4";
        $params = [
            "field3"    =>  "three",
            "field_4"   =>  "four",
            "field1"    =>  "one",
            "field2"    =>  "two",
        ];

        $args = [&$query, &$params];
        $this->callProtectedMethod("namedParams", $args);
        $query = $this->callProtectedMethod("prepareQuery", $args);

        $this->assertEquals($check, $query);
    }


    public function testEmptyStrings()
    {
        $this->sql->mode = "sqlite";
        $this->sql->definitions([
            "table1"    =>  "database1",
            "table2"    =>  "database2",
            "table3"    =>  "database3",
        ]);

        $check = "SELECT a.* FROM `database1`.`table1` a
                JOIN `database2`.`table2` b ON b.field1=a.field1 AND b.field2=''
                JOIN `database3`.`table3` c ON c.field1=a.field1
                WHERE a.field1='TEST'";

        $query = "SELECT a.* FROM {table1} a
                JOIN {table2} b ON b.field1=a.field1 AND b.field2=''
                JOIN {table3} c ON c.field1=a.field1
                WHERE a.field1='TEST'";

        $args = [&$query];
        $this->callProtectedMethod("tableNames", $args);
        $this->assertEquals($check, $query);
    }


    public function testStrictWhere()
    {
        $this->sql->mode = "mysql";

        $check = "`field1` IN (?, ?) ";
        $params = [0, 1];

        $where = [
            "field1"    =>  [0, 1],
        ];

        $null = [];
        $args = [&$where, &$null];
        $query = $this->callProtectedMethod("where", $args);
        $this->assertEquals($check, $query);
    }


    public function testExists1()
    {
        $this->sql->mode = "sqlite";

        $table = "table1";
        $params = ["field1" => "row1"];

        $this->sql->insert($table, $params);

        $this->assertEquals(true, $this->sql->exists($table, $params));
    }


    public function testExists2()
    {
        $this->sql->mode = "sqlite";

        $table = "table1";
        $params = ["field1" => "row1"];

        $this->sql->delete($table, $params);

        $this->assertEquals(false, $this->sql->exists($table, $params));
    }


    public function testDisconnect1()
    {
        $sql = new Intruder($this->sql);

        $sql->mode = "mysql";
        $sql->connected = false;
        $sql->server = (object) ["connect_error" => null];

        $this->assertFalse($this->sql->disconnect());
    }
    public function testDisconnect2()
    {
        $sql = new Intruder($this->sql);

        $sql->mode = "mysql";
        $sql->connected = true;
        $sql->server = null;

        $this->assertFalse($this->sql->disconnect());
    }
    public function testDisconnect3()
    {
        $sql = new Intruder($this->sql);

        $sql->mode = "mysql";
        $sql->connected = true;
        $sql->server = (object) ["connect_error" => "Couldn't Connect"];

        $this->assertFalse($this->sql->disconnect());
    }
    public function testDisconnect4()
    {
        $sql = new Intruder($this->sql);

        $sql->mode = "mysql";
        $sql->connected = true;

        $server = Mockery::mock();
        $server->connect_error = null;
        $server->shouldReceive("close")->andReturn(false);
        $sql->server = $server;

        $this->assertFalse($this->sql->disconnect());
    }
    public function testDisconnect5()
    {
        $sql = new Intruder($this->sql);

        $sql->mode = "mysql";
        $sql->connected = true;

        $server = Mockery::mock();
        $server->connect_error = null;
        $server->shouldReceive("close")->andReturn(true);
        $sql->server = $server;

        $this->assertTrue($this->sql->disconnect());
        $this->assertFalse($sql->connected);
    }
}
