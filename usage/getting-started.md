---
layout: default
title: Getting Started
permalink: /usage/getting-started/
api: Sql
---

## Queries

Running a basic query and getting the results:

~~~php
$result = $sql->query("SELECT stuff FROM table WHERE id = ?", [$id]);
foreach ($result as $row) {
    echo $row["stuff"] . "\n";
}
~~~

Updating records in a table:

~~~php
$sql->update("table", [
    "field2"    =>  "new-value",
], [
    "field1"    =>  "today",
]);

# UPDATE `table` SET `field2` = 'new-value' WHERE `field1` = 'today'
~~~

Inserting new records into a table:

~~~php
$sql->insert("table", [
    "field1"    =>  "tomorrow",
    "field2"    =>  "value",
]);

# INSERT INTO `table` (`field1`, `field2`) VALUES ('new-value', 'today')
~~~

Deleting records from a table:

~~~php
$sql->delete("table", [
    "field1"    =>  "yesterday",
]);

# DELETE FROM `table` WHERE `field1` = 'yesterday'
~~~
