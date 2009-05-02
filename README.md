postgresql-php-tools
====================

last modified: 02.05.2009
version: 0.0.1

Andreas Wenk (Hamburg, Germany)

a.wenk@netzmeister-st-pauli.de
http://www.netzmeister-st-pauli.de

This is a small PHP class for interacting with a PostgreSQL
database.

Preface:
--------
Keep it simple. This class is as simple as possible but provides an 
easy way to interact with the PostgreSQL database. By the way - it's
quite easy to adopt this class and it's methods for MySQL. Originally
this was written for MySQL.

The target is to make live during coding a little easier and giving the ability
not having to use the PHP pg_methods directly.

Following are some basic examples for the usage.

How to use:
-----------
Basically, you first open a connection to the database and then use the 
class methods in the well known OOP way.

	Create the object:
		For security reasons it's good practice to have this in a
		conf file outside the DOCUMENT_ROOT 

		$db = new mod_pgsql();
		$db->connect(DB_HOST, DB_PORT, DB_DATABASE, DB_USER, DB_PASS);	
	
	SELECT statement:
		The result is written into a array (in this case a associative array)

		$qs = "SELECT * from table";
		$res = $db->select($qs);

	INSERT, UPDATE DELETE:
		These statements are all encapsulated in a transaction. Therfore, we put
		the statements into a array and then call the transaction method. If the
		transaction is commited it returns true and if not false.

		$qs = " INSERT INTO table (id, data) VALUES ({$id}, '{$data}')";
		$db->sql_add($qs);

		$qs = " UPDATE table SET data = '{$new_data}' WHERE id = {$id}";
		$db->sql_add($qs);

		$qs = " DELETE FROM table WHERE id = {$id}";
		$db->sql_add($qs);

		$db->transaction();
		
	PREPARED STATEMENT:
		Let's say we have a lot of INSERT statements which we fire within a
		foreach loop. Generally, using a prepared statement is helping to
		improve performance.

		$qs = " INSERT INTO table (id,data) VALUES ($1, $2)";
		$db->prepare($qs, 'prep_name');

		foreach($data_array as $k => $v) {
			$db->execute('prep_name', array($k, $v));
		}		
		
		Using prepared statements with a SELECT statement is also supportetd


Changelog:
----------
02.05.2009: 0.0.1
	- fix some typo's in README.md
30.04.2009: 0.0.1
	- first commit 
	- fill the README
