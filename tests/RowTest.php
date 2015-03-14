<?php

namespace duncan3dc\SqlClassTest;

use duncan3dc\SqlClass\Row;

class RowTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $this->row = new Row([
            "field1"    =>  "val1 ",
            "field2"    =>  130,
            "field3"    =>  "test",
        ]);
    }

    public function testPropertyAccess1()
    {
        $this->assertSame("val1", $this->row->field1);
    }
    public function testPropertyAccess2()
    {
        $this->assertSame(130, $this->row->field2);
    }
    public function testPropertyAccess3()
    {
        $this->assertSame("test", $this->row->field3);
    }
    public function testPropertyAccess4()
    {
        $this->setExpectedException("InvalidArgumentException", "Invalid field: field4");
        $this->row->field4;
    }

    public function testArrayAccess1()
    {
        $this->assertSame("val1", $this->row["field1"]);
    }
    public function testArrayAccess2()
    {
        $this->assertSame(130, $this->row["field2"]);
    }
    public function testArrayAccess3()
    {
        $this->assertSame("test", $this->row["field3"]);
    }
    public function testArrayAccess4()
    {
        $this->setExpectedException("InvalidArgumentException", "Invalid field: field4");
        $this->row["field4"];
    }

    public function testIndexAccess1()
    {
        $this->assertSame("val1", $this->row[0]);
    }
    public function testIndexAccess2()
    {
        $this->assertSame(130, $this->row[1]);
    }
    public function testIndexAccess3()
    {
        $this->assertSame("test", $this->row[2]);
    }
    public function testIndexAccess4()
    {
        $this->setExpectedException("InvalidArgumentException", "Invalid index: 3");
        $this->row[3];
    }

    public function testRaw1()
    {
        $this->assertSame("val1 ", $this->row->raw("field1"));
    }
    public function testRaw2()
    {
        $this->assertSame(130, $this->row->raw("field2"));
    }
    public function testRaw3()
    {
        $this->assertSame("test", $this->row->raw("field3"));
    }
    public function testRaw4()
    {
        $this->setExpectedException("InvalidArgumentException", "Invalid field: field4");
        $this->row->raw("field4");
    }

    public function testUnset1()
    {
        $this->assertSame("val1", $this->row->field1);
        unset($this->row->field1);
        $this->setExpectedException("InvalidArgumentException", "Invalid field: field1");
        $this->row->field1;
    }
    public function testUnset2()
    {
        $this->assertSame("val1", $this->row[0]);
        unset($this->row[0]);
        $this->setExpectedException("InvalidArgumentException", "Invalid index: 0");
        $this->row[0];
    }
    public function testUnset3()
    {
        $this->assertSame("val1", $this->row["field1"]);
        unset($this->row["field1"]);
        $this->setExpectedException("InvalidArgumentException", "Invalid field: field1");
        $this->row["field1"];
    }
}
