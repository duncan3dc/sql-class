sql-class
=========

A simple database abstraction layer, with an on disk caching facility.  

Full documentation is available at http://duncan3dc.github.io/sql-class/  
PHPDoc API documentation is also available at [http://duncan3dc.github.io/sql-class/api/](http://duncan3dc.github.io/sql-class/api/namespaces/duncan3dc.SqlClass.html)  

[![Build Status](https://img.shields.io/travis/duncan3dc/sql-class.svg)](https://travis-ci.org/duncan3dc/sql-class)
[![Latest Version](https://img.shields.io/packagist/v/duncan3dc/sql-class.svg)](https://packagist.org/packages/duncan3dc/sql-class)


TODO
----
* Remove any lingering switch statements for different modes
* Instead of juggling all the different syntaxes, just define a supported syntax, and then convert from that to others where necessary
  eg support LIMIT 10, but not FETCH FIRST 10 ROWS, and support `rist4f2.rap84` but not [rist4f2].[rap84]
* Sort the tests out, coverage is low and quality is poor
* Docblocks
* Is a QueryBuilder concept useful? The Sql class is doing too much. Also if we've built a query ourselves we don't need to convert any syntax, it can be safely run
* Add a PDO engine
* Better injection/creation of classes/engines. It should be possible to insert any compatible engine class (maybe a factory for creating results?)
* Should the creation stuff be moved out to a factory class?
* Performance of foreach() vs while(fetch) isn't good enough
* Have we lost support for mssql table quoting (eg database.dbo.table)


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
