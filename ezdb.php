<?php

// Proxy Helper

class EzDBProxyHelper
{
  function __construct($db, $table_name)
  {
    $this->db = $db;
    $this->table_name = $table_name;
    $this->class_name = $this->db->getClassName($table_name);
    $this->primary_key = $this->db->getPrimaryKey($table_name);
  }
}

class EzDBProxyHelperMaker
{
  function __construct($db, $command)
  {
    $this->class_name = 'EzDB'.$command.'ProxyHelper';
    if (!class_exists($this->class_name))
      trigger_error("EzDB: command {$command} not supported", E_USER_ERROR);
    if (!is_subclass_of($this->class_name, 'EzDBProxyHelper'))
      trigger_error("EzDB: class {$class_name} must be a subclass of EzDBProxyHelper", E_USER_ERROR);
    $this->db = $db;
    $this->command = $command;
  }

  function __call($table_name, $args)
  {
    $obj = new $this->class_name($this->db, $table_name);
    return call_user_func_array($obj, $args);
  }

  function __get($table_name)
  {
    return new $this->class_name($this->db, $table_name);
  }
}

// Query
class EzDBQueryProxyHelper extends EzDBProxyHelper
{
  function __invoke($sql)
  {
    return $this->db->ListFromSql($sql, $this->class_name, $this->table_name, $this->primary_key);
  }
}

// Get
class EzDBGetProxyHelper extends EzDBProxyHelper
{
  function __invoke($cond)
  {
    if (!is_array($cond))
      $cond = array($this->primary_key => $cond);
    return $this->db->ObjectFromArray($this->class_name, $this->table_name, $this->primary_key, $cond);
  }

  public function __call($method, $args)
  {
    $cond = array($method => $args[0]);
    return $this->db->ObjectFromArray($this->class_name, $this->table_name, $this->primary_key, $cond);
  }

}

// List
class EzDBListProxyHelper extends EzDBProxyHelper
{
  function __invoke()
  {
    $args = func_get_args();

    $cond = array();
    // format cond in case primary key is implied
    if (isset($args[0]) && !is_array($args[0]))
      $cond = array($this->primary_key => $args[0]);
    elseif (isset($args[0]))
      $cond = $args[0];

    return $this->db->ListFromArray($this->class_name, $this->table_name, $this->primary_key, $cond);
  }

  public function __call($method, $args)
  {
    $cond = array($method => $args[0]);
    // pass limit if set
    $limit = null;
    if (isset($args[1]))
      $limit = $args[1];
    // pass the order
    $order = null;
    if (isset($args[2]))
      $order = $args[2];
    return $this->db->ListFromArray($this->class_name, $this->table_name, $this->primary_key, $cond);
  }

}

// Create
class EzDBCreateProxyHelper extends EzDBProxyHelper
{
  function __invoke()
  {
    $args = func_get_args();

    $values = array();
    if (isset($args[0]) && !is_array($args[0]))
      $values = array($this->primary_key => $args[0]);
    elseif (isset($args[0]))
      $values = $args[0];

    return $this->db->CreateFromArray($this->class_name, $this->table_name, $this->primary_key, $values);
  }

  public function __call($method, $args)
  {
    $values = array();
    if (isset($args[0]) && !is_array($args[0]))
      $values = array($this->primary_key => $args[0]);
    elseif (isset($args[0]))
      $values = $args[0];

    $on_duplicate_key_update = false;
    if (isset($args[1]) && $args[1] === true) 
      $on_duplicate_key_update = true;
    return $this->db->CreateFromArray($this->class_name, $this->table_name, $this->primary_key,
                                      $values, $on_duplicate_key_update);
  }

}

// Count
class EzDBCountProxyHelper extends EzDBProxyHelper
{
  function doCount($cond)
  {
    $this->class_name = 'stdClass';
    $sql = $this->db->queryBuilder->count($this->class_name, $this->table_name, $this->primary_key, $cond);
    $obj = $this->db->ObjectFromSql($sql, $this->class_name, $this->table_name, $this->primary_key);
    if ($obj === false)
      return false;
    return $obj->count;
  }

  function __invoke($cond)
  {
    if (!is_array($cond))
      $cond = array($this->primary_key => $cond);
    return $this->docount($cond);
//    return $this->db->ObjectFromArray($this->class_name, $this->table_name, $this->primary_key, $cond);
  }

  public function __call($method, $args)
  {
    $cond = array($method => $args[0]);
    return $this->docount($cond);
    //return $this->db->ObjectFromArray($this->class_name, $this->table_name, $this->primary_key, $cond);
  }

}

// Each
class EzDBEachProxyHelper extends EzDBProxyHelper
{
  function doEach($cond, $callback)
  {
    if (is_array($cond))
      $sql = $this->db->queryBuilder->select($this->class_name, $this->table_name, $this->primary_key, $cond);
    else
      $sql = $cond;
    $param = null;
    return $this->db->Each($sql, $callback, $param, $this->class_name, $this->table_name, $this->primary_key);
  }

  function __invoke($cond, $callback)
  {
    return $this->doEach($cond, $callback);
  }

/*  public function __call($method, $args)
  {
    $cond = array($method => $args[0]);
    return $this->doEach($cond);
  }*/

}

// From Public Id
class EzDBPublicProxyHelper extends EzDBProxyHelper
{
  function __invoke($crypted_id)
  {
    $cond = array($this->primary_key => $this->db->decrypt($crypted_id));
    return $this->db->ObjectFromArray($this->class_name, $this->table_name, $this->primary_key, $cond);
  }
}

class EzDBQueryBuilder
{
  function __construct($db)
  {
    $this->db = $db;
  }

  function update($obj, $table_name, $cond, $fields)
  {
    $sql = '';
    $infos = $this->db->getTableInfo($table_name);
    foreach ($fields as $name)
    {
      // we unknow skip fields
      if (!array_key_exists($name, $infos))
      {
        trigger_error("field {$name} does not exist in table {$table_name}", E_USER_WARNING);
        continue;
      }
      if ($sql != '')
        $sql .= ' , ';
      $escp_val = $this->db->PhpToDB( $this->db->uncast($table_name, $name, $obj->$name) );
      $sql .= ' `' . $name . '` = ' . $escp_val . ' ';
    }
    $sql = "UPDATE `{$table_name}` SET " . $sql . ' WHERE 1 ';
    foreach ($cond as $name => $val)
    {
      
      $sql .= ' AND `' . $name . '` = ' . $val . ' ';
    }
    return $sql;
  }

  function create($class_name, $table_name, $primary_key, $values, $on_duplicate_key_update = false)
  {
    $infos = $this->db->getTableInfo($table_name);
    // generate query
    $sql = '';
    $sql_val = '';
    $sql_dup = '';
    foreach ($values as $name => $val)
    {
      if (!array_key_exists($name, $infos))
      {
        trigger_error("field {$name} does not exist in table {$table_name}", E_USER_WARNING);
        continue;
      }
      if ($sql != '')
      {
        $sql .= ' , ';
        $sql_val .= ' , ';
        $sql_dup .= ' , ';
      }

      $escp_val = $this->db->PhpToDB( $this->db->uncast($table_name, $name, $val) );
      $sql .= ' `' . $name . '` ';
      $sql_val .= ' ' . $escp_val . ' ';
      $sql_dup .= ' `' . $name . '` = ' . $escp_val . ' ';

    }
    $sql = "INSERT INTO `{$table_name}` ({$sql}) VALUES ({$sql_val})";
    if ($on_duplicate_key_update === true)
      $sql .= " ON DUPLICATE KEY UPDATE `{$primary_key}` = LAST_INSERT_ID(`{$primary_key}`), {$sql_dup} ";
    return $sql;
  }

  function delete($table_name, $cond)
  {
    $infos = $this->db->getTableInfo($table_name);
    if (!is_array($cond) || count($cond) == 0)
      trigger_error("EzDB: missing delete condition", E_USER_ERROR);
    $sql = "DELETE FROM `{$table_name}` WHERE 1 ";
    foreach ($cond as $name => $val)
    {
      // we unknow skip fields
      if (!array_key_exists($name, $infos))
      {
        trigger_error("field {$name} does not exist in table {$table_name}", E_USER_WARNING);
        continue;
      }
      $escp_val = $this->db->PhpToDB( $this->db->uncast($table_name, $name, $val) );
      $sql .= ' AND `' . $name . '` = ' . $escp_val . ' ';
    }
    return $sql;
  }

  function select($class_name, $table_name, $primary_key, $cond, $limit = null, $order = null)
  {
    $infos = $this->db->getTableInfo($table_name);
    // generate query
    if ($this->db->auto_get_found_rows == true)
      $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM `{$table_name}` WHERE 1 ";
    else
      $sql = "SELECT * FROM `{$table_name}` WHERE 1 ";
    foreach ($cond as $name => $val)
    {
      // we unknow skip fields
      if (!array_key_exists($name, $infos))
      {
        trigger_error("field {$name} does not exist in table {$table_name}", E_USER_WARNING);
        continue;
      }
      if ($val === null)
        $sql .= ' AND `' . $name . '` IS NULL ';
      else
      {
        $escp_val = $this->db->PhpToDB( $this->db->uncast($table_name, $name, $val) );
        $sql .= ' AND `' . $name . '` = ' . $escp_val . ' ';
      }
    }
    if (is_array($order))
      if (sizeof($order) > 0)
      {
        $sql .= ' ORDER BY ';
        foreach ($order as $name => $val)
        {
          $sql .= '`' . $name . '`  ' . $val . ', ';
        }
        $sql .= ' 1 ';
      }
    if (is_string($order))
      $sql .= ' ORDER BY `' . $order . '`';
    if (is_array($limit) && isset($limit[0]) && isset($limit[1]))
      $sql .= ' LIMIT ' . ((int) $limit[0]) . ', ' . ((int) $limit[1]) . ' ';
    if (is_numeric($limit))
      $sql .= " LIMIT $limit ";
    return $sql;
  }

  function count($class_name, $table_name, $primary_key, $cond, $limit = null, $order = null)
  {
    $infos = $this->db->getTableInfo($table_name);
    // generate query
    $sql = "SELECT COUNT(*) AS count FROM `{$table_name}` WHERE 1 ";
    foreach ($cond as $name => $val)
    {
      // we unknow skip fields
      if (!array_key_exists($name, $infos))
        continue;
      if ($val === null)
        $sql .= ' AND `' . $name . '` IS NULL ';
      else
      {
        $escp_val = $this->db->PhpToDB( $this->db->uncast($table_name, $name, $val) );
        $sql .= ' AND `' . $name . '` = ' . $escp_val . ' ';
      }
    }
    return $sql;
  }

}

class EzDB
{
  const CURRENT_EZDB = '__ezdb_current_ptr';

  // configuration
  public  $auto_get_found_rows = false;
  public  $enable_query_log = false;
  public  $query_log_path = '~/data/log/ezdb/';
  public  $query_log_file;  
  public  $enable_query_cache = true;
  public  $fill_list_with_primary_key = false;
  public  $autoload_class_path = false;
  public  $table_prefix = false;
  public  $debug_callback = false;
  public  $query_cache_path = false;  


  //internal
  public  $mysqli;
  public  $db;
  public  $cached_table;
  public  $dbkey;
  public  $no_cache = false;
  public  $default_cache_ttl = 300;

  // connection level
  const NONE = 0;
  const READ = 1;
  const WRITE = 2;

  private $current_connection_level = EzDB::NONE;

  private $_get;
  private $_create;
  private $_list;

  private $mysql_host;
  private $mysql_login;
  private $mysql_password;
  private $mysql_dbname;
  private $mysql_port;

  static public $instances = false;

  function __construct($login, $password, $dbname, $host = 'localhost', $port = 3306)
  {
    $this->db = $this;
    $this->cached_table = array();
    $this->dbkey = 'ezdb2' . $login . '#' . $dbname . '#' . $host;

    // connect is done only if needed
    $this->mysqli = false;

    // store mysql var
    $this->mysql_host = $host;
    $this->mysql_login = $login;
    $this->mysql_password = $password;
    $this->mysql_dbname = $dbname;
    $this->mysql_port = $port;

    $this->queryBuilder = new EzDBQueryBuilder($this);

    $this->get = new EzDBProxyHelperMaker($this, 'get');
    $this->list = new EzDBProxyHelperMaker($this, 'list');
    $this->create = new EzDBProxyHelperMaker($this, 'create');
    $this->query = new EzDBProxyHelperMaker($this, 'query');
    $this->count = new EzDBProxyHelperMaker($this, 'count');
    $this->foreach = new EzDBProxyHelperMaker($this, 'each');
    $this->public = new EzDBProxyHelperMaker($this, 'public');

    // auto cache internals
    $this->AddCachedTable('#ezdbinternal#');

    // auto enable query cache
    if (isset($_GET['_enable_query_log']) && $_GET['_enable_query_log'] == '1')
      $this->enable_query_log = true;

    // no cache
    if (isset($_GET['_no_cache']) && $_GET['_no_cache'] == '1')
      $this->no_cache = true;

    $this->query_log_file = "sql_".$this->dbkey.".log";

    // class autoloader
    spl_autoload_register(array($this, 'autoloader'));

  }

  function setReadOnlyConfiguration($login, $password, $dbname, $host = 'localhost', $port = 3306)
  {
    $this->mysql_host_ro = $host;
    $this->mysql_login_ro = $login;
    $this->mysql_password_ro = $password;
    $this->mysql_dbname_ro = $dbname;
    $this->mysql_port_ro = $port;
  }

  function connect($required_level)
  {
    // no read only configuration ?
    if (!isset($this->mysql_host_ro))
      $required_level = EzDB::WRITE;   
    // current connection is enough
    if ($this->current_connection_level >= $required_level)
      return;   
    // we need to reconnect
    $time_start = microtime(true);    
    if ($this->mysqli !== false)
      $this->mysqli->close();
    $this->mysqli = new mysqli();
    // connect
    if ($required_level == EzDB::WRITE)
      $this->mysqli->connect($this->mysql_host, $this->mysql_login, $this->mysql_password, $this->mysql_dbname, $this->mysql_port);
    elseif ($required_level == EzDB::READ)
      $this->mysqli->connect($this->mysql_host_ro, $this->mysql_login_ro, $this->mysql_password_ro, $this->mysql_dbname_ro, $this->mysql_port_ro);
    if (mysqli_connect_errno())
      trigger_error('EzDB: Fatal: Database connection failed: ' . mysqli_connect_error(), E_USER_ERROR);

    // configure SQL connection
    if ($this->mysqli->set_charset('utf8') == FALSE)
      trigger_error("EzDB: Fatal: set mysqli charset failed", E_USER_ERROR);

    $time_end = microtime(true);
    // log query if needed
    $this->queryLog($time_start, $time_end, "CONNECT: ".($required_level == EzDB::READ ? $this->mysql_login_ro : $this->mysql_login)." - ".($required_level == EzDB::READ ? $this->mysql_dbname_ro : $this->mysql_dbname)." @{$this->mysqli->host_info} ({$this->mysqli->server_info})");

    $this->current_connection_level = $required_level;

    $this->Query('SET time_zone = "Europe/Paris"', EzDB::READ);

    // init tables infos
    $this->tables_infos = $this->getTablesInfos();    
  }

  function ping()
  {
    $this->Query('SELECT NOW( )', EzDB::READ);
  }

  function reconnect($required_level)
  {
    if ($this->mysqli != false)
      $this->mysqli->close();
    ini_set('mysql.allow_persistent', FALSE);
    $this->mysqli = false;
    $this->connect($required_level);
  }  

  function getLastError()
  {
    return $this->mysqli->error;
  }

  function debug($msg)
  {
    if ($this->debug_callback !== false)
      $this->debug_callback($msg);
    else
    {
      print htmlspecialchars($msg, ENT_COMPAT|ENT_IGNORE, "UTF-8")."<br />\r\n";
    }
  }

  function queryLog($time_start, $time_end, $query)
  {
    //var_dump("queryLog: {$query}");
    if (!$this->enable_query_log)
      return;
    $this->debug(sprintf("Execution Time: [%.04f] sec\r\nQuery: %s", $time_end - $time_start, $query));
    if ($this->query_log_path)
      file_put_contents($this->query_log_path."/".$this->query_log_file, date('[Y-m-d H:i:s]') . sprintf("[%d] [%.02f] [%s]", posix_getpid(), $time_end - $time_start, $query) . "\r\n", FILE_APPEND);   
  }

  function handleSQLError($sql)
  {
    if ($this->mysqli->errno == 2006)
      trigger_error("EzDB: Query Error: " . $this->mysqli->error . "\r\nQuery was:\r\n{$sql}", E_USER_WARNING);
    trigger_error("EzDB: Query Error: " . $this->mysqli->error . "\r\nQuery was:\r\n{$sql}", E_USER_WARNING);
    return false;
  }

  public  function AddCachedTable($table_name, $ttl = false)
  {
    if ($ttl === false)
      $tll = $this->default_cache_ttl;
    $this->cached_table[$table_name] = $ttl;
  }

  public  function DeleteCachedTable($table_name)
  {
    unset($this->cached_table[$table_name]);
  }

  function Query($query, $required_level = EzDB::WRITE)
  {
    $this->Connect($required_level);
    $time_start = microtime(true);
    $result = $this->mysqli->query($query);
    $time_end = microtime(true);

    // log query if needed
    $this->queryLog($time_start, $time_end, $query);

    if ($result === false)
      return $this->handleSQLError($query);

    return true;
  }

  function MultiQuery($query, $required_level = EzDB::WRITE)
  {
    $this->Connect($required_level);
    $time_start = microtime(true);

    /* execute multi query */
    $result = false;
    if ($this->mysqli->multi_query($query)) {
      $result = true;
      do {
          if ($this->mysqli->use_result()) {
              $result->free();
          }
          if ($this->mysqli->more_results() == false)
            break;
      } while ($result = $this->mysqli->next_result());
    } else {
      return false; // first query failed
    }

    // todo: check error ?
    //if ($this->mysqli->error != '')
    //  return false;

    $time_end = microtime(true);

    // log query if needed
    $this->queryLog($time_start, $time_end, $query);

    if ($result === false)
      return $this->handleSQLError($query);

    return true;
  }

  function ObjectFromSql($sql, $class_name = 'stdClass', $table_name = false, $primary_key = false)
  {
    $array = $this->ListFromSql($sql, $class_name, $table_name, $primary_key);

    if (count($array) == 0)
      return false;

    if (count($array) > 1)
      trigger_error("EzDB: ObjectFromSql: should return only one row: ".count($array)." returned.\r\nQuery was:\r\n{$sql}", E_USER_WARNING);

    $obj = array_shift($array);

    return $obj;    
  }

  function ListFromSql($sql, $class_name = 'stdClass', $table_name = false, $primary_key = false, $fill_list_with_primary_key = null)
  {
    if ($fill_list_with_primary_key === null)
      $fill_list_with_primary_key = $this->fill_list_with_primary_key;

    // detect if we are in EzDB mode or anonyme mode
    // in ezdb mode, object have special class
    if ($table_name && $primary_key && is_subclass_of($class_name, 'EzDBObj') || strcasecmp($class_name, 'EzDBObj') === 0)
      $ezdb_mode = true;
    else
      $ezdb_mode = false;

    // if this table is cached, try to return result from cache
    $key = '.ezdb#ListFromSql#' . $this->dbkey . '#' . $table_name . '#' . $sql;    
    if ($this->enable_query_cache && $table_name && isset($this->cached_table[$table_name]) && !$this->no_cache)
    {
      $GLOBALS[EzDB::CURRENT_EZDB] = $this;
      $array = apc_fetch($key, $success);
      // founded ?
      if ($success === true)
        return $array;
    }

    // store result in an array
    $data = new stdClass;
    $data->array = array();
    $data->fill_list_with_primary_key = $fill_list_with_primary_key;
    $data->primary_key = $primary_key;

    $this->Each($sql, function($obj, &$data) {
        $primary_key = $data->primary_key;
        // if we have a primary key, use it as index
        if ($data->fill_list_with_primary_key && $primary_key && isset($obj->$primary_key))
          $data->array[$obj->$primary_key] = $obj;
        else
          $data->array[] = $obj;
    }, $data, $class_name, $table_name, $primary_key);
    
    // do we need to put result in cache ?
    if (!$this->no_cache && $this->enable_query_cache && $table_name && isset($this->cached_table[$table_name]))
    {
      apc_store($key, $data->array, $this->cached_table[$table_name]);
      $this->addCacheTag($table_name, $key);
    }

    if ($ezdb_mode)
      return $this->initEzDBObjArray($data->array);

    return $data->array;
  }

  function Each($sql, $callback, &$param, $class_name = 'stdClass', $table_name = false, $primary_key = false)
  {
    // start loging execution time
    $time_start = microtime(true);

    // detect if we are in EzDB mode or anonyme mode
    // in ezdb mode, object have special class
    if ($table_name && $primary_key && (is_subclass_of($class_name, 'EzDBObj') || strcasecmp($class_name, 'EzDBObj') === 0))
      $ezdb_mode = true;
    else
      $ezdb_mode = false;

    // connect if needed
    $this->Connect(EzDB::READ);

    // execute statement and get metadata
    $result = $this->mysqli->query($sql);

    // handle error
    if ($result === false)
      return $this->handleSQLError($sql);

    $fields_meta = $result->fetch_fields();

    // get statement execution time
    $time_end = microtime(true);

    // log query if needed
    $this->queryLog($time_start, $time_end, $sql);
    
    // extract all field from object and give it to object
    if ($ezdb_mode)
    {
      $fields = $this->getTableFields($table_name);
      while ($row = $result->fetch_row())
      {
        // create object
        $obj = new $class_name();

        // keep track of sub ezdb objects
        $sub_ezdb_obj = array();

        // from the result row, we extract data and try to store them
        foreach ($fields_meta as $idx => $field)
        {
          if ($field->name === false)
            ;
//          else if ($ezdb_mode === false)
//            $obj->{$field->name} = $this->cast($field, $row[$idx]);
          elseif ($field->table == $table_name)
            $obj->{$field->name} = $this->cast($field, $row[$idx]);
          else if ($field->table !== false && $field->name !== false && !isset($fields[$field->table]))
          {
            // we don't create sub entry if for null 
            //if (!isset($obj->{$field->table}) && $row[$idx] === null)
            //  continue;
            if (!isset($obj->{$field->table}))
            {
              $sub_class_name = $this->getClassName($field->orgtable);
              $sub_obj = new $sub_class_name;
              $sub_ezdb_obj []= $field->table;
              $obj->{$field->table} = $sub_obj;
            }
            $obj->{$field->table}->{$field->name} = $this->cast($field, $row[$idx]);
          }
          else if ($field->name !== false)
            $obj->{$field->name} = $this->cast($field, $row[$idx]);
        }

        // init ezdb object
        $this->preInitEzDBObj($obj, $fields, $table_name, $primary_key, $sub_ezdb_obj);

        if ($callback($obj, $param) === false)
          return false;
      }
    } else {
      while ($obj = $result->fetch_object($class_name))
      {
        if ($callback($obj, $param) === false)
          return false;
      }      
    }

    return true;
  }


  function cast($field, $value)
  {
    // null value should stay null
    if ($value === null)
      return null;    
    // first we try to cast from information put in comment
    if (isset($this->tables_infos[$field->table]['fields_infos'][$field->name]))
    {
      // metas are store in field comment as a query string
      $field_info = $this->tables_infos[$field->table]['fields_infos'][$field->name];
      $ezdb_metas = false;
      parse_str($field_info['comment'], $ezdb_metas);
      if (is_array($ezdb_metas))
      {
        if (isset($ezdb_metas['compress']) && $ezdb_metas['compress'] == 1)
        {
          $value_compress = @gzuncompress(substr($value, 4));//gzinflate($value);
          if ($value_compress !== false)
            $value = $value_compress;
        }
        if (isset($ezdb_metas['type']) && $ezdb_metas['type'] == 'json')
          return json_decode($value);
        if (isset($ezdb_metas['type']) && $ezdb_metas['type'] == 'json_array')
          return json_decode($value, true);
      }
    }

    /*
tinyint_    1
boolean_    1
smallint_    2
int_        3
float_        4
double_        5
real_        5
timestamp_    7
bigint_        8
serial        8
mediumint_    9
date_        10
time_        11
datetime_    12
year_        13
bit_        16
decimal_    246
text_        252
tinytext_    252
mediumtext_    252
longtext_    252
tinyblob_    252
mediumblob_    252
blob_        252
longblob_    252
varchar_    253
varbinary_    253
char_        254
binary_        254
*/
    // boolean
    if ($field->type == 1)
      return (bool)$value;
    // int
    if ($field->type == 2 || $field->type == 3)
      return (int)$value;
    // float
    if ($field->type == 4 || $field->type == 5 || $field->type == 246)
      return (float)$value;

    // datetime
    if ($field->type == 12 && $value != false)
      //return new EzDBDateTime($value);
    return (string)$value;
    
    // text/blob varchar char/binary
    if ($field->type == 252 || $field->type == 253 || $field->type == 254)
      return $value;

    //var_dump($field_info);
    //var_dump($field);
    return $value;
  }

  function uncast($table_name, $field_name, $value)
  {
    // null value should stay null
    if ($value === null)
      return null;
    // first we try to cast from information put in comment
    if (isset($this->tables_infos[$table_name]['fields_infos'][$field_name]))
    {
      // metas are store in field comment as a query string
      $field_info = $this->tables_infos[$table_name]['fields_infos'][$field_name];
      $ezdb_metas = false;
      parse_str($field_info['comment'], $ezdb_metas);
      if (is_array($ezdb_metas))
      {
        if (isset($ezdb_metas['type']) && $ezdb_metas['type'] == 'json')
          $value = json_encode($value);
        if (isset($ezdb_metas['compress']) && $ezdb_metas['compress'] == 1)
          $value = "\x1f\x8b\x08\x00".gzcompress($value);//gzdeflate($value);
      }
    }
    return (string)$value;
  }

  function arrayCopy( array $array )
  {
          $result = array();
          foreach( $array as $key => $val ) {
              if( is_array( $val ) ) {
                  $result[$key] = arrayCopy( $val );
              } elseif ( is_object( $val ) ) {
                  $result[$key] = clone $val;
              } else {
                  $result[$key] = $val;
              }
          }
          return $result;
  }

  function preInitEzDBObj($obj, $fields, $table_name, $primary_key, $sub_ezdb_obj)
  {
    $infos = array();
    $infos['table_name'] = $table_name;

    $original_fields_values = array();
    foreach ($fields as $field)
    {
      $original_fields_values[$field] = (is_object($obj->$field) ? clone $obj->$field : $obj->$field);
    }
    $infos['original_fields_values'] = $original_fields_values;

    //$infos['original_fields_values'] = $this->arrayCopy($fields);

    // pre init sub ezdb objects
    if ($sub_ezdb_obj)
        foreach ($sub_ezdb_obj as $sub_table_name)
          if ($this->tableExist($sub_table_name))
          {
            $sub_table_primary_key = $this->getPrimaryKey($sub_table_name);
            if ($obj->$sub_table_name->{$sub_table_primary_key} === null)
              $obj->$sub_table_name = null;
            else
              $this->preInitEzDBObj($obj->$sub_table_name, $this->getTableFields($sub_table_name), $sub_table_name, $sub_table_primary_key, false);
          }

    $infos['sub_ezdb_obj'] = $sub_ezdb_obj;
    
    $obj->getInfos($infos);

    return $obj;
  }

  // init object return by EzDB
  function initEzDBObj($obj)
  {
    $obj->_db($this);
    //$obj->db = $this;
    $obj->EZdbInit();
    // init sub ezdb objects
    $sub_ezdb_obj = $obj->getInfos();
    $sub_ezdb_obj = $sub_ezdb_obj['sub_ezdb_obj'];
    if ($sub_ezdb_obj)
      foreach ($sub_ezdb_obj as $sub_table_name)
      {
        $sub_obj = $obj->$sub_table_name;
        /*if (is_object($sub_obj))
        {
          $class_name = get_class($sub_obj);
          if (is_subclass_of($class_name, 'EzDBObj') || strcasecmp($class_name, 'EzDBObj') === 0)*/
            if ($sub_obj && $this->tableExist($sub_table_name))
              $this->initEzDBObj($sub_obj);
        //}
      }
    // clean object if needed (happen if object just have been stored via apc)
    unset($obj->_ezdb_infos);
    return $obj;
  }

  // init an array of object return by EzDB
  function initEzDBObjArray($array)
  {
    foreach ($array as $obj)
    {
      $this->initEzDBObj($obj);
    }
    return $array;
  }

  function ObjectFromArray($class_name, $table_name, $primary_key, $cond)
  {
    $sql = $this->queryBuilder->select($class_name, $table_name, $primary_key, $cond);
    return $this->ObjectFromSql($sql, $class_name, $table_name, $primary_key);
  }

  function ListFromArray($class_name, $table_name, $primary_key, $cond)
  {
    $sql = $this->queryBuilder->select($class_name, $table_name, $primary_key, $cond);
    return $this->ListFromSql($sql, $class_name, $table_name, $primary_key);
  }

  function UpdateFromArray($obj, $table_name, $cond, $fields)
  {
    if ($this->enable_query_cache && $table_name && isset($this->cached_table[$table_name]))
    {
      $this->deleteCacheTag($table_name);
    }
    $sql = $this->queryBuilder->update($obj, $table_name, $cond, $fields);
    return $this->Query($sql);
  }

  function CreateFromArray($class_name, $table_name, $primary_key, $values, $on_duplicate_key_update = false)
  {
    if ($this->enable_query_cache && $table_name && isset($this->cached_table[$table_name]))
    {
      $this->deleteCacheTag($table_name);
    }    
    $sql = $this->queryBuilder->create($class_name, $table_name, $primary_key, $values, $on_duplicate_key_update);
    $this->Query($sql);
    $insert_id = $this->mysqli->insert_id;
    if (array_key_exists($primary_key, $values))
      $insert_id = $values[$primary_key];
    return $this->ObjectFromArray($class_name, $table_name, $primary_key, array($primary_key => $insert_id));
  }

  function DeleteFromArray($table_name, $cond)
  {
    if ($this->enable_query_cache && $table_name && isset($this->cached_table[$table_name]))
    {
      $this->deleteCacheTag($table_name);
    }    
    $sql = $this->queryBuilder->delete($table_name, $cond);
    return $this->Query($sql);
  }

  function PhpToDB($val)
  {
    if ($val === null)
      return ' NULL ';
    else
      return '\'' . addslashes($val) . '\'';
  }

  function tableExist($table_name)
  {
    return isset($this->tables_infos[$table_name]);
  }

  function getTableInfo($table_name)
  {
    if (!isset($this->tables_infos[$table_name]))
      trigger_error("EzDB: table '{$table_name}' not found", E_USER_ERROR);
    if (isset($this->tables_infos[$table_name]['fields_infos']))
      return $this->tables_infos[$table_name]['fields_infos'];
    trigger_error("EzDB: tables fields infos not found for table '{$table_name}'", E_USER_ERROR);
  }

  function getPrimaryKey($table_name)
  {
    // first we fetch in table infos allowing cache
    if (!isset($this->tables_infos[$table_name]))
      $this->tables_infos = $this->getTablesInfos();
    // not found, retry with cache disabled
    if (!isset($this->tables_infos[$table_name]))
      $this->tables_infos = $this->getTablesInfos(true);
    if (!isset($this->tables_infos[$table_name]))
    {
      //debug_print_backtrace();
      trigger_error("EzDB: table '{$table_name}' not found", E_USER_ERROR);
    }
    if (isset($this->tables_infos[$table_name]['primary_key']))
      return $this->tables_infos[$table_name]['primary_key'];
    /*// not found ... let's retry without cache
    $this->tables_infos = $this->getTablesInfos(true);
    if (isset($this->tables_infos[$table_name]['primary_key']))
      return $this->tables_infos[$table_name]['primary_key'];*/
    trigger_error("EzDB: primary key not found for table '{$table_name}'", E_USER_ERROR);
  }

  function getTableFields($table_name)
  {
    // first we fetch in table infos allowing cache
    if (!isset($this->tables_infos[$table_name]))
      $this->tables_infos = $this->getTablesInfos();
    // not found, retry with cache disabled
    if (!isset($this->tables_infos[$table_name]))
      $this->tables_infos = $this->getTablesInfos(true);
    // still not found, should be an error ...    
    if (!isset($this->tables_infos[$table_name]))
    {
      debug_print_backtrace();
      trigger_error("EzDB: table '{$table_name}' not found", E_USER_ERROR);    
    }
    if (isset($this->tables_infos[$table_name]['table_fields']))
      return $this->tables_infos[$table_name]['table_fields'];
    trigger_error("EzDB: tables fields not found for table '{$table_name}'", E_USER_ERROR);
  }

  function searchClassName($table_name, &$class_path, &$class_name)
  {
    // search auto class path declaration
    $class_path = false;
    // try with suffix
    $prefix = strpos($table_name, '_');
    if ($prefix !== false)
    {
      $short_name = substr($table_name, $prefix + 1);
      $prefix = substr($table_name, 0, $prefix);
      if (file_exists($this->autoload_class_path.'/'.$prefix.'/'.$short_name.'.php'))
        $class_path = $this->autoload_class_path.'/'.$prefix.'/'.$short_name.'.php';
    }
    if ($class_path === false && file_exists($this->autoload_class_path.'/'.$this->mysql_dbname.'/'.$table_name.'.php'))
      $class_path = $this->autoload_class_path.'/'.$this->mysql_dbname.'/'.$table_name.'.php';
    if ($class_path === false && file_exists($this->autoload_class_path.'/'.$this->mysql_dbname.'/'.$table_name.'.php'))
      $class_path = $this->autoload_class_path.'/'.$this->mysql_dbname.'/'.$table_name.'.php';
    if ($class_path === false && file_exists($this->autoload_class_path.'/'.$table_name.'.php'))
      $class_path = $this->autoload_class_path.'/'.$table_name.'.php';
    
    // auto load it
    if ($class_path !== false)
      require_once($class_path);

    // search class name
    $class_name = 'EzDB'.$table_name;
    if (class_exists($class_name))
    {
      if (!is_subclass_of($class_name, 'EzDBObj'))
        trigger_error("EzDB: class {$class_name} must be a subclass of EzDBObj", E_USER_ERROR);
      return true;
    }

    $class_name = 'EzDBObj';
    return true;
  }

  function getClassName($table_name)
  {
    static $table_class_assoc = false;
    static $key = false;

    // fetch from apc if needed
    if ($table_class_assoc == false)
    {
      $key = $this->dbkey.'#table_class_assoc';
      if (!$this->no_cache)
      {
        $table_class_assoc = apc_fetch($key, $success);
        if ($success == false)
          $table_class_assoc = array();
      }
      else
        $table_class_assoc = array();
    }

    // assoc was in cache ?
    if (isset($table_class_assoc[$table_name]))
    {
      if ($table_class_assoc[$table_name]['class_path'] !== false)
        require_once($table_class_assoc[$table_name]['class_path']);
      return $table_class_assoc[$table_name]['class_name'];
    }

    // else search for it
    $this->searchClassName($table_name, $class_path, $class_name);

    // store it in cache
    $table_class_assoc[$table_name]['class_name'] = $class_name;
    $table_class_assoc[$table_name]['class_path'] = $class_path;
    if (!$this->no_cache)
      apc_store($key, $table_class_assoc, $this->default_cache_ttl);

    return $class_name;
  }

  // auto load ezdb class, usefull for static method calls
  function autoloader($name)
  {
    $name = strtolower($name);
    if (strpos($name, 'ezdb') === 0)
    {
      $class_name = $this->getClassName(str_replace('ezdb', '', $name));
    }
  }

  function getTablesInfos($no_cache = false)
  {
    static $tables_infos = false;

    if ($tables_infos !== false && !$no_cache)
      return $tables_infos;

    $key = $this->dbkey.'#tables_infos';

    // try from query_cache_path
    if ($this->query_cache_path !== false && $tables_infos == false && !$this->no_cache && !$no_cache)
    {
      $php_cache_path = $this->query_cache_path.'/'.$key.'.php';
      if (file_exists($php_cache_path))
      {
        $tables_infos = include($php_cache_path);
        if ($tables_infos !== false)
          return $tables_infos;
      }
    }

    // try to fetch from apc
    if ($tables_infos == false && !$this->no_cache && !$no_cache)
    {
      $tables_infos = apc_fetch($key, $success);
      if ($success)
        return $tables_infos;
    }

    if ($no_cache)
    {
      $old_no_cache = $this->no_cache;
      $this->no_cache = true;
    }
    $tables_infos_datas = $this->ListFromSql('SHOW TABLES;', 'stdClass', '#ezdbinternal#');
    if ($no_cache)
      $this->no_cache = $old_no_cache;

    // foreach tables
    foreach ($tables_infos_datas as $obj)
      foreach ((array)$obj as $table_name)
      {
        if ($this->table_prefix !== false && strrpos($table_name, $this->table_prefix) !== 0)
          continue;

        // get tables infos
        //$fields_infos = $this->ListFromSql('DESCRIBE `' . addslashes($table_name) . '`;', 'stdClass', '#ezdbinternal#', 'Field', true);
        $fields_infos = array();
        $fields_infos_objs = $this->ListFromSql('SHOW FULL COLUMNS FROM `' . addslashes($table_name) . '`;', 'stdClass', '#ezdbinternal#', 'Field', true);
        foreach ($fields_infos_objs as $field => $field_info)
        {
          foreach ($field_info as $k => $v)
          {
            if ($k == 'Comment' || $k == 'Key' || $k == 'Field')
              $fields_infos[$field][strtolower($k)] = $v;
          }
        }
        
        // prepara primary key and fields list
        $primary_key = false;
        $table_fields = array();
        foreach ($fields_infos as $field)
        {
          if ($field['key'] == 'PRI')
            $primary_key = $field['field'];
          $table_fields [/*$field['field']*/]= $field['field'];
        }

        $tables_infos[$table_name] = array( 'table_fields' => $table_fields,
                                            'primary_key' => $primary_key,
                                            'fields_infos' => $fields_infos);
      }

    if ($this->query_cache_path !== false && !$this->no_cache)
    {
      $php_cache_path = $this->query_cache_path.'/'.$key.'.php';
      $php_cache_data = '<?php $return = '.var_export($tables_infos, true).'; return $return;';
      file_put_contents($php_cache_path, $php_cache_data);
    }

    if (!$this->no_cache)
      apc_store($key, $tables_infos, $this->default_cache_ttl);

    return $tables_infos;
  }


/*
  function getTableList()
  {
    $sql = 'SHOW TABLES;';
    $res = $this->ListFromSql($sql, 'stdClass', '#ezdbinternal#');
    $array = array();
    foreach ($res as $obj)
      foreach ((array)$obj as $table_name)
        $array []= $table_name;
    return $res;
  }*/

   function GetAffectedRows()
  {
    return intval($this->mysqli->affected_rows);
  }

   function GetFoundsRow()
  {
    $res = $this->ObjectFromSql('SELECT FOUND_ROWS() AS nb;');
    return intval($res->nb);
  }

  // manage public id
  function encrypt($id)
  {
    return bin2hex(openssl_encrypt($id, 'aes-256-cbc', SHARED_SECRET, OPENSSL_RAW_DATA, SHARED_IV));
  }

  function decrypt($_id)
  {
    return openssl_decrypt(hex2bin($_id), 'aes-256-cbc', SHARED_SECRET, OPENSSL_RAW_DATA, SHARED_IV);
  }

  // cache invalidation
  function addCacheTag($tag, $key)
  {
    $tagkey = $this->dbkey.'#cache_tag#'.$tag;

    $tagkeys = apc_fetch($tagkey, $success);
    if ($success === false)
      $tagkeys = array();

    $tagkeys[$key] = $key;

    apc_store($tagkey, $tagkeys, $this->default_cache_ttl * 2 + 600);
  }

  function deleteCacheTag($tag)
  {
    $tagkey = $this->dbkey.'#cache_tag#'.$tag;

    $tagkeys = apc_fetch($tagkey, $success);
    if ($success !== false)
        apc_delete($tagkeys);
  }

  // deprecated
  public  static function ImportFromArray($class_name, $table_name, $primary_key, $cond)
  {
    trigger_error("EzDB: ImportFromArray deprecated, use ObjectFromArrayintead", E_USER_DEPRECATED);
    global $db;
    return $db->ObjectFromArray($class_name, $table_name, $primary_key, $cond);
  }

  public  static function QueryToObj($sql, $class_name = 'stdClass')
  {
    trigger_error("EzDB: QueryToObj deprecated, use ObjectFromSql intead", E_USER_DEPRECATED);
    global $db;
    return $db->ObjectFromSql($sql, $class_name);
  }

  public  static function QueryToObjArray($sql, $primary_key = null, $class_name = 'stdClass')
  {
    trigger_error("EzDB: QueryToObjArray deprecated, use ListFromSql intead", E_USER_DEPRECATED);
    global $db;
    return $db->ListFromSql($sql, $class_name, $primary_key);
  }  

}

// store date fields
class EzDBDateTime extends DateTime
{
  function add($arg)
  {
    if (is_string($arg))
      $arg = DateInterval::createFromDateString($arg);
    return parent::add($arg);
  }

  function sub($arg)
  {
    if (is_string($arg))
      $arg = DateInterval::createFromDateString($arg);
    return parent::sub($arg);
  }

  function __toString()
  {
    return $this->format(MYSQL_DATE_FORMAT);
  }
}


class EzDBObj
{
  // auto generate getXXX and listXXX function
  function __call($name, $arguments)
  {
    if (strtolower(substr($name, 0, 3)) == 'get')
    {
      $table = strtolower(substr($name, 3));
      $foreign_key = $table . '_id';
      $table_info = $this->db->GetTableInfo($table);
      if (is_array($table_info))
      {
        $primary_key = false;
        foreach ($table_info as $field)
          if ($field['key'] == 'PRI')
            $primary_key = $field['field'];
        if ($primary_key && isset($this->$foreign_key))
          return $this->db->get->$table->$primary_key($this->$foreign_key);
      }
    }
    if (strtolower(substr($name, 0, 4)) == 'list')
    {
      $command = 'list';
      $table = strtolower(substr($name, 4));
      //$primary_key = $this->db->getPrimaryKey($this->_ezdb['table_name']);
      $foreign_key = $table . '_id';
      $limit = null;
      $order = null;
      if (isset($arguments[0]))
        $limit = $arguments[0];
      if (isset($arguments[1]))
        $order = $arguments[1];
      return $this->db->list->$table->$foreign_key($this->$foreign_key, $limit, $order);
    }
    trigger_error('EzDB: function EzDB' . $this->_ezdb['table_name'] . '->' . $name . '(...) not found !', E_USER_ERROR);
  }

  function __toString()
  {
    $primary_key = $this->db->getPrimaryKey($this->_ezdb['table_name']);
    return get_class($this)."[{$this->_ezdb['table_name']}#" . $this->$primary_key . "]";
  }

  function _db(EzDB $_db = null)
  {
    static $db;

    if ($_db !== null)
    {
      $db = $_db;
    }
    return $db;
  }

  function __get($name)
  {
    if ($name == 'db')
      return $this->_db();
    if ($name == '_ezdb')
      return $this->getInfos();
  }

/*  function _ezdb_set_table_fields($table_fields)
  {
    $this->_ezdb['table_fields'] = $table_fields;
  }*/

/*  function _ezdb_set_primary_key($primary_key)
  {
    $this->db->getPrimaryKey($this->_ezdb['table_name']) = $primary_key;
  }*/

/*
  function _ezdb_set_table_name($table_name)
  {
    $this->_ezdb['table_name'] = $table_name;
  }

  function _ezdb_set_sub_ezdb_obj($sub_ezdb_obj)
  {
    $this->_ezdb['sub_ezdb_obj'] = $sub_ezdb_obj;
  }

  function _ezdb_set_original_fields_values($original_fields_values)
  {
    $this->_ezdb['original_fields_values'] = $original_fields_values;
  }
*/
  function getInfos($_infos = null)
  {
    static $infos;

    $hash = spl_object_hash($this);
    if ($_infos !== null)
    {
      $infos[$hash] = $_infos;
    }
//debug_print_backtrace();
    return $infos[$hash];
  }

  // override this to perform custom init code
  function EZdbInit()
  {

  }

  function publicId()
  {
    $primary_key = $this->db->getPrimaryKey($this->_ezdb['table_name']);
    return $this->db->encrypt($this->$primary_key);
  }

  function EzDBToArray()
  {
    $ret = array();
    $table_fields = $this->db->getTableFields($this->_ezdb['table_name']);
    foreach ($table_fields as $field)
    {
      $ret[$field] = $this->$field;
    }
    return $ret;
  }

  function save($values = array())
  {
    $primary_key = $this->db->getPrimaryKey($this->_ezdb['table_name']);
    $new_original_fields_values = $original_fields_values = $this->_ezdb['original_fields_values'];

    // detect update fields
    $updated_fields = array();
    foreach ($original_fields_values as $name => &$value)
    {
      if (!($this->$name === $value) && $name != $primary_key)
      {
        $new_original_fields_values[$name] = $this->$name;
        $updated_fields[] = $name;
//        $this->_ezdb['original_fields_values'][$name] = $this->$name;
      } else {
        $new_original_fields_values[$name] = $value;
      }
    }

    // return 0 if no fields have been updated
    if (count($updated_fields) == 0)
      return 0;
    
    // ask ezdb to update require fields
    $ret = $this->db->UpdateFromArray($this, $this->_ezdb['table_name'], array($primary_key => $this->$primary_key), $updated_fields);

    if ($ret === false)
      return false;

    $_ezdb = $this->getInfos();
    $_ezdb['original_fields_values'] = $new_original_fields_values;
    $this->getInfos($_ezdb);

    return count($updated_fields);
  }

  function sync($values = array())
  {
    $primary_key = $this->db->getPrimaryKey($this->_ezdb['table_name']);
    $table_fields = $this->db->getTableFields($this->_ezdb['table_name']);
    foreach ($values as $k => $v)
    {
      if (!in_array($k, $table_fields))
      {
        trigger_error("field {$k} does not exist in table {$this->_ezdb['table_name']}", E_USER_WARNING);
        continue;
      }
      if ($k != $primary_key)
        $this->$k = $v;
    }
    return $this->db->UpdateFromArray($this, $this->_ezdb['table_name'], array($primary_key => $this->$primary_key), $table_fields);
  }

  function update($values = array())
  {
    return $this->save($values);
  }

  function delete()
  {
    $primary_key = $this->db->getPrimaryKey($this->_ezdb['table_name']);
    return $this->db->DeleteFromArray($this->_ezdb['table_name'], array($primary_key => $this->$primary_key));
  }

  function duplicate($values = array())
  {
    // get primary key
    $primary_key = $this->db->getPrimaryKey($this->_ezdb['table_name']);
    // get fields
    $fields = $this->db->getTableFields($this->_ezdb['table_name']);

    // fill $datas with all attribute
    $datas = array();
    foreach ($fields as $name)
    {
      if ($name == $primary_key)
        continue;
      if (isset($values[$name]))
        $datas[$name] = $values[$name];
      else
        $datas[$name] = $this->$name;
    }

    // create and return object
    $table_name = $this->_ezdb['table_name'];
    return $this->db->create->$table_name($datas);
  }

  function __sleep()
  {
    // add infos in obj
    $this->_ezdb_infos = $this->getInfos();
    $fields = array_keys(get_object_vars($this));
    return $fields;
  }

  function __wakeup()
  {
    $db = $GLOBALS[EzDB::CURRENT_EZDB];
    $this->_db($db);
    $this->getInfos($this->_ezdb_infos);
    unset($this->_ezdb_infos);
    $this->EZdbInit();
  }
}

