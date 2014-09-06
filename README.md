ezdb
====

EzDB is a mysql helper for PHP.

In most project :
-	90% of MySQL query are simple and boring query like SELECT * FROM table WHERE primary_key = 42.
-	The rest are more advance query with JOIN, ORDER, GROUP BY and other stuff like this

EzDB allow you to do the first 90% of query with a simple and easy to use PHP API and let you use regular SQL for the rest.

Why ?
-------

1 - SQL is not a bad language and should not avoid at all cost.
2 – Developpers are lazy and write too simple query is anoying.

Requirement :
-------

-	EzDB need APC
-	You must configure primary key in your mysql table

Basic Usage :
-------

```php
// connect
$db = new EzDB(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST, DB_PORT);

// fetch
$car = $db->get->car(42) ; // get car with primary key 42

// update
$car->model = "My Model";
$car->save() ;

// duplicate
$new_car = $car->duplicate() ;

// delete
$new_car->delete() ; 

// create
$datas = array('model' => 'Famous Brand') ;
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

// auto get query
$brand = $car->getBrand() ;
$cars_for_this_brand = $brand->listCar();


```

Custom class
-------

By default object class returned by EzDB command are EzDBObj. If you need to use custom class, the name must be EZDB followed by name of the table, and the class must extends EzDB:

```php

class EzDBCar extend EzDBObj
{

  // thie method will be called when object his instanciate
  function EZdbInit()
  {

  }

  function print()
  {
    echo $this->model.' - '.$this->getBrand()->name;
  }

}

```

Advanced Query
-------

Some SQL query return a result that have no link with a table, in this case magic method will not exist.

```php

$car_count = $db->ObjectFromSql("SELECT COUNT(*) AS ct FROM car");

print $car_count->ct;

$cars_names = $db->listFromSql("SELECT title FROM brand WHERE title like 'p%' ORDER BY title");

foreach ($cars_names as $car_name)
{
  print $car_name->title;
}


```
