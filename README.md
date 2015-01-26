sql-class
=========

A simple database abstraction layer, with an on disk caching facility.  

Full documentation is available at http://duncan3dc.github.io/sql-class/  
PHPDoc API documentation is also available at [http://duncan3dc.github.io/sql-class/api/](http://duncan3dc.github.io/sql-class/api/namespaces/duncan3dc.SqlClass.html)  

[![Build Status](https://img.shields.io/travis/duncan3dc/sql-class.svg)](https://travis-ci.org/duncan3dc/sql-class)
[![Latest Version](https://img.shields.io/packagist/v/duncan3dc/sql-class.svg)](https://packagist.org/packages/duncan3dc/sql-class)


Examples
--------

The classes use a namespace of duncan3dc\SqlClass
```php
use duncan3dc\SqlClass\Sql;
```

-------------------

```php
$sql = new Sql([
    "mode"      =>  "mysql",
    "hostname"  =>  "localhost",
    "username"  =>  "root",
    "password"  =>  "password",
]);

$row = $sql->select("table_1",[
    "field1"    =>  "one",
    "field2"    =>  "two",
]);

$sql->update("table_1",[
    "field3"    =>  "three",
],[
    "field1"    =>  $row["field1"],
    "field2"    =>  $row["field2"],
])
```
