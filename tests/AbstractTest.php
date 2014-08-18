<?php

namespace duncan3dc\SqlClassTest;

use duncan3dc\SqlClass\Sql;

abstract class AbstractTest extends \PHPUnit_Framework_TestCase
{
    protected $sql;
    protected $database;
    protected $reflection;
    protected $engine;


    public function setUp()
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
            "table2"    =>  "test2",
        ]);

        $this->reflection = new \ReflectionClass($this->sql);
        $this->engine = $this->reflection->getProperty("engine");
        $this->engine->setAccessible(true);

        $this->setMode("sqlite");

        $this->sql->connect();

        $this->sql->query("CREATE TABLE test1.table1 (field1 VARCHAR(10), field2 INT)");
    }


    public function tearDown()
    {
        $this->setMode(null);
        unset($this->sql);
        unlink($this->database);
    }


    protected function setMode($mode)
    {
        $this->sql->mode = $mode;

        $class = "\\duncan3dc\\SqlClass\\Engine\\" . ucfirst($mode) . "\\Sql";
        if (class_exists($class)) {
            $engine = new $class;
        } else {
            $engine = null;
        }
        $this->engine->setValue($this->sql, $engine);
    }


    protected function callProtectedMethod($methodName, &$params)
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);

        if (is_array($params)) {
            return $method->invokeArgs($this->sql, $params);
        } elseif ($params) {
            return $method->invokeArgs($this->sql, [&$params]);
        } else {
            return $method->invokeArgs($this->sql);
        }
    }
}
