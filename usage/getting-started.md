---
layout: default
title: Getting Started
permalink: /usage/getting-started/
api: Sql
---

After you've instantiated your Sql object most of the actions you be run on it.

## Queries

Running a basic query and getting the results:

~~~php
 $result = $sql->query("SELECT stuff FROM table WHERE id=?", [$id]);
 foreach ($result as $row) {
     echo $row["stuff"] . "\n";
 }
~~~
