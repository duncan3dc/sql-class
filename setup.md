---
layout: default
title: Setup
permalink: /setup/
---

All classes are in the `duncan3dc\SqlClass` namespace.

While you can create new instance using the Sql constructor, it is often better to define the available servers first:

~~~php
 require_once __DIR__ . "vendor/autoload.php";

 use duncan3dc\SqlClass\Sql;

 Sql::addServer("lemieux", [
     "mode"      =>  "mysql",
     "hostname"  =>  "lemieux-db",
     "username"  =>  $_ENV["DB_USERNAME"],
     "password"  =>  $_ENV["DB_USERNAME"],
 ]);
 Sql::addServer("jagr", [
     "mode"      =>  "mssql",
     "hostname"  =>  "jagr-db:16014",
     "username"  =>  $_ENV["DB_USERNAME"],
     "password"  =>  $_ENV["DB_USERNAME"],
 ]);
~~~

Then you can use the Sql class like a factory to create your instances for you:

~~~php
 # By default we use first server defined (useful if you only define one server)
 $sql = Sql::getInstance();

 # This will return the exact same object as above
 $sql = Sql::getInstance("lemieux");

 # Or we can get a completely new instance
 $sql = Sql::getNewInstance("lemieux");
~~~
