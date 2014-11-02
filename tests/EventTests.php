<?php

namespace duncan3dc\SqlClass;

class EventTest extends \PHPUnit_Framework_TestCase
{
    protected $sql;


    public function __construct()
    {
        $tmp = "/tmp/phpunit.events.sqlite";
        if (file_exists($tmp)) {
            unlink($tmp);
        }
        $this->sql = new \duncan3dc\SqlClass\Sql([
            "mode"      =>  "sqlite",
            "database"  =>  $tmp,
        ]);
        $this->sql->query("CREATE TABLE test1 (field1 TEXT)");

        $this->sql->connect();
    }


    public function testInsertBefore()
    {
        $test = "";
        $this->sql->addListener("insert.before.test1", function($event) use(&$test) {
            $test .= "ok";
        });
        $this->sql->insert("test1", [
            "field1" => "ok",
        ]);
        $this->assertSame("ok", $test);
    }


    public function testInsertAfter()
    {
        $test = "";
        $this->sql->addListener("insert.after.test1", function($event) use(&$test) {
            $test .= "ok";
        });
        $this->sql->insert("test1", [
            "field1" => "ok",
        ]);
        $this->assertSame("ok", $test);
    }
}
