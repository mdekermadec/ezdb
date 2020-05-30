EzDB
====

EzDB is an easy to use mysql helper for PHP.

Philosophy behind this project is clear: let developers focus on where they add values.

In most projects:
 - 90% of MySQL queries are simple and boring SQL statements like SELECT * FROM table WHERE primary_key = 42.
 - The other part is more advance queries with JOIN, ORDER, GROUP BY …

EzDB allow you to do 90% of query with a simple and easy to use PHP API and let you use regular SQL for the advance part.

It is built with performance in mind and is already used in many heavy load applications.

Why ?
-------

 - SQL is not a bad language and should not be avoided at all cost.
 - Developers are lazy and write simple query is annoying.

Requirement :
-------

 - EzDB need APCU: https://www.php.net/manual/fr/book.apcu.php
 - You should configure primary keys in your mysql table in you want to enable object magic methods.

Basic Usage :
-------

```php
// connect
$db = new EzDB(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST, DB_PORT);

// fetch
$car = $db->get->car(42); // get the car with primary key 42

// update
$car->model = "My Model";
$car->save() ;

// duplicate
$new_car = $car->duplicate() ;

// delete
$new_car->delete() ; 

// create
$datas = array('model' => 'Famous Model') ;
$famous_car = $db->create->car($datas) ;

// query with a join works, you get sub objects for each JOIN
$sql = 'SELECT * FROM car
LEFT JOIN brand ON brand.id = car.brand_id';
$cars = $db->query->car($sql) ;

// iterate
foreach ($cars as $my_car)
{
	print "<h4>{$my_car->model} – {$my_car->brand->name}</h4>";
}

// auto get columns from another table (using foreign keys)
$brand = $car->getBrand() ;
$cars_for_this_brand = $brand->listCar();

// manual query
$db->query("UPDATE car SET model = CONCAT('The ', model) WHERE 1");

// multi query
$db->MultiQuery("UPDATE car SET model = CONCAT('Das ', model) WHERE 1; UPDATE car SET model = CONCAT('Le ', model) WHERE 1");

// count query
$nb_car = $db->count->car->model("My Model");

// foreach (will use less memory, useful for big dataset)
$db->foreach->car($sql, function ($car) {
  print "{$car->model}<br />";
});

```

Custom class
-------

By default object class returned by EzDB command are EzDBObj. If you need to use custom class, classname must be EZDB followed by the name of the table, and class must extend EzDBObj:

```php

class EzDBCar extend EzDBObj
{

  // this method will be called when the object is created
  function EZdbInit()
  {

  }

  function print()
  {
    echo $this->model.' - '.$this->getBrand()->name;
  }

}

$car = $db->get->car(42);

$car->print();
```

Advanced Query
-------

Some SQL queries returns a result that have no link with any table, in this case magic method (delete(), duplicate(), ...) will not be available.

```php

$car_count = $db->ObjectFromSql("SELECT COUNT(*) AS ct FROM car");

print $car_count->ct;

$cars_names = $db->listFromSql("SELECT title FROM brand WHERE title LIKE 'p%' ORDER BY title");

foreach ($cars_names as $car_name)
{
  print $car_name->title;
}
```

Cache
-------

EzDB can cache mysql result for you. To do that, you need to register which tables can be cached and for how long.

```php
// enable cache for table brand 
$db->AddCachedTable('brand', 3600 /* 3600 second cache, default is 300 */);

// disable cache for table car 
$db->DeleteCachedTable('car');
```

As EzDB only connect to mysql server when needed, if you cache the right table you can easily create pages that can render without connecting to mysql. This is especially interesting for a front page.

Master/Slave MySQL Server
-------

EzDB support Master/Slave MySQL configuration. You can set up a slave mysql configuration that is READ ONLY. EzDB will connect to the right server automatically.

```php
$db->setReadOnlyConfiguration(DB_USER_READ_ONLY, DB_PASSWORD_READ_ONLY, DB_NAME_READ_ONLY, DB_HOST_READ_ONLY, DB_PORT_READ_ONLY);

// this will connect to read server
$car = $db->get->car(42);

$car->model = "Model 42";

// this will switch mysql server to read/write server (default server)
$car->save();

// if you get another car, connection will remain on read/write server
$car = $db->get->car(21);

```

Debug and Query Log
-------

When you develop your service, it can be annoying to suffer from deprecated cache (like if you change your database scheme and ezdb still not aware of it).
To avoid this you can:
 - Disable cache completely
```php
$db->no_cache = true;
```
 - Adjust default TTL to a lower value (300 seconds by default)
```php
$db->default_cache_ttl = 60;
```

To check what is happening between ezdb and your mysql server, you can also enable query log:
```php
$db->enable_query_log = true;
$db->query_log_path = '/var/log/ezdb';
```

All SQL query will be printed on the screen and stored on disk.

Class Loader
-------

You can tell ezdb where you store your custom EzDB class:
```php
$db->autoload_class_path = '/var/www/project/class';
```
Class loader work this way:
 - PHP Filename must be the name of the table.
 - If in the table name, there is an underscore ( _ ), it will optionally cut this to check subdirectories.
 - If file found it is required once and if a class with according to the name: EzDB + table name exists, it will be used as EzDB custom class.

Meta data
-------

You can add meta data via table row comment, this meta data help EzDB do some automatic data treatment:

 - compress=1: EzDB will compress and decompress data on the fly
 - type=json: EzDB will json encode/decode data on the fly
 - type=json_array: EzDB will json encode/decode as an array data on the fly

Other options
-------
 - When listing a table, you can ask EzDB to use primary key value a key index for php array:
```php
$db->fill_list_with_primary_key = true;
```
 - EzDB can automatically set SQL_CALC_FOUND_ROWS in each SQL query so that you can find out how much entry are found in your query:
```php
$db->auto_get_found_rows = true;
$total = $db->GetAffectedRows();
```
- to change the default cache TTL, 300 seconds by default:
```php
$db->default_cache_ttl = 300;
```
