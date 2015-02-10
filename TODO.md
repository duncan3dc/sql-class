* Instead of juggling all the different syntaxes, just define a supported syntax, and then convert from that to others where necessary
  eg support LIMIT 10, but not FETCH FIRST 10 ROWS, and support `rist4f2.rap84` but not [rist4f2].[rap84]

* Finish the interfaces
  - Ensure all type hints use them

* Sort the tests out, coverage is low and quality is poor

* Docblocks

* Is a QueryBuilder concept useful? The Sql class is doing too much. Also if we've built a query ourselves we don't need to convert any syntax, it can be safely run

* Create a helper class to handle things like orderBy() search() selectFields() where()

* What are we doing with fetchStyle

* "indiviual"

* Add a PDO engine

* Better injection/creation of classes/engines. It should be possible to insert any compatible engine class (maybe a factory for creating results?)

* Should the creation stuff be moved out to a factory class?

* Performance of foreach() vs while(fetch) isn't good enough

* Have we lost support for mssql table quoting (eg database.dbo.table)

* Can we drop the NO_WHERE_CLAUSE constant? Are there some methods we can use for this? Maybe force passing an empty array?

* Counting an odbc update statement that updated 0 rows fails, as it doens't know whether there's 0 rows, or the check failed, so it tries to fetch, and you can't fetch from an update statement
