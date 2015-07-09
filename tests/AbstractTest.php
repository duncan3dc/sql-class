<?php

namespace duncan3dc\SqlClassTests;

use duncan3dc\SqlClass\Engine\Sqlite\Server;
use duncan3dc\SqlClass\Sql;

abstract class AbstractTest extends \PHPUnit_Framework_TestCase
{
    protected $sql;
    protected $database;
    protected $reflection;
    protected $engine;
    protected $connected;


    public function setUp()
    {
        $this->database = "/tmp/phpunit_" . microtime(true) . ".sqlite";
        if (file_exists($this->database)) {
            unlink($this->database);
        }

        $server = new Server("/tmp/phpunit.sqlite");
        $this->sql = new Sql($server);

        $this->sql->definitions([
            "table1"    =>  "test1",
            "table2"    =>  "test2",
        ]);

        $this->reflection = new \ReflectionClass($this->sql);
        $this->engine = $this->reflection->getProperty("engine");
        $this->engine->setAccessible(true);
        $this->connected = $this->reflection->getProperty("connected");
        $this->connected->setAccessible(true);

        $this->setMode("sqlite");
        $this->sql->query("CREATE TABLE {table1} (field1 VARCHAR(10), field2 INT)");
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

        $class = "\\duncan3dc\\SqlClass\\Engine\\" . ucfirst($mode) . "\\Server";

        if (class_exists($class)) {
            $engine = new $class("hostname", "username", "password");
            $engine->setSql($this->sql);
        } else {
            $engine = null;
        }
        $this->engine->setValue($this->sql, $engine);

        if ($mode === "sqlite") {
            $this->connected->setValue($this->sql, false);
            $this->sql->attachDatabase($this->database, "test1");
        }
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
