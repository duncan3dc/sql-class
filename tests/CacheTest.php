<?php

namespace duncan3dc\SqlClassTests;

use duncan3dc\SqlClass\Cache;
use duncan3dc\SqlClass\Sql;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    private $sql;
    private $database;


    public function setUp(): void
    {
        $this->database = "/tmp/phpunit_" . microtime(true) . ".sqlite";
        if (file_exists($this->database)) {
            unlink($this->database);
        }

        $this->sql = new Sql([
            "mode"      =>  "sqlite",
            "database"  =>  "/tmp/phpunit.sqlite",
        ]);

        $this->sql->attachDatabase($this->database, "test1");

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
        unlink($this->database);
    }


    public function testCount()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);
        $this->sql->insert("table1", ["field1" => "row2"]);

        $result = $this->sql->selectAllC("table1", Sql::NO_WHERE_CLAUSE);
        $this->assertSame(2, $result->count());
    }

    public function testColumnCount()
    {
        $result = $this->sql->selectAllC("table1", Sql::NO_WHERE_CLAUSE);
        $this->assertSame(2, $result->columnCount());
    }

    public function testFetchAssoc1()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);
        $result = $this->sql->selectAllC("table1", Sql::NO_WHERE_CLAUSE);
        $this->assertSame("row1", $result->fetch()["field1"]);
    }

    public function testFetchAssoc2()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);
        $result = $this->sql->selectAllC("table1", Sql::NO_WHERE_CLAUSE);
        $this->assertSame("row1", $result->fetch(Sql::FETCH_ASSOC)["field1"]);
    }

    public function testFetchAssoc3()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);
        $result = $this->sql->selectAllC("table1", Sql::NO_WHERE_CLAUSE);
        $result->fetchStyle(Sql::FETCH_ASSOC);
        $this->assertSame("row1", $result->fetch()["field1"]);
    }

    public function testFetchRow1()
    {
        $this->sql->insert("table1", ["field1" => "row1"]);
        $result = $this->sql->selectAllC("table1", Sql::NO_WHERE_CLAUSE);
        $this->assertSame("row1", $result->fetch(true)[0]);
    }

    public function testFetchRow2()
    {
        $this->sql->insert("table1", ["field1" => "testFetchRow2", "field2" => "ok"]);
        $result = $this->sql->selectAllC("table1", ["field1" => "testFetchRow2"]);
        $this->assertSame("ok", $result->fetch(1)[1]);
    }

    public function testFetchRow3()
    {
        $this->sql->insert("table1", ["field1" => "testFetchRow3", "field2" => "ok"]);
        $result = $this->sql->selectAllC("table1", ["field1" => "testFetchRow3"]);
        $this->assertSame("ok", $result->fetch(Sql::FETCH_ROW)[1]);
    }

    public function testFetchRow4()
    {
        $this->sql->insert("table1", ["field1" => "testFetchRow4", "field2" => "ok"]);
        $result = $this->sql->selectAllC("table1", ["field1" => "testFetchRow4"]);
        $result->fetchStyle(Sql::FETCH_ROW);
        $this->assertSame("ok", $result->fetch()[1]);
    }

    public function testDirectoryPermissions()
    {
        $base = "/tmp/phpunit_cache";
        $cache = new Cache([
            "dir"   =>  $base,
            "sql"   =>  $this->sql,
            "query" =>  "SELECT " . time() . " FROM table1",
        ]);

        # Remove the base path for the cache directories path
        $path = substr($cache->dir, strlen($base) + 1);

        # Break up the path into each directory so we can test them all
        $dirs = explode("/", $path);
        array_unshift($dirs, $base);

        $path = "";
        foreach ($dirs as $dir) {
            if (strlen($path) > 0) {
                $path .= "/";
            }
            $path .= $dir;
            $this->assertSame("0777", substr(sprintf("%o", fileperms($path)), -4));
        }
    }
}
