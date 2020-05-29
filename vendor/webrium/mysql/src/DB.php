<?php
namespace webrium\mysql;

use webrium\mysql\Query;
use webrium\mysql\QueryBuilder;

class DB
{
  // Database config array
  private static $db_config=[];

  /**
  * Init Database Connections
  * @param array $config [ 'config_name'=>[driver,db_host,db_host_port,db_name,username,password,charset],[...] ]
  *
  **/
  public static function setConfig($config,$getArray=false)
  {
    DB::$db_config=$config;
  }

  public static function getConfig()
  {
    return DB::$db_config;
  }

  /**
  * To use multiple databases
  * @param  string $config_name config key name
  * @return query  class query
  */
  public static function in($config_name)
  {
    return  DB::initOnceDB($config_name);
  }

  /**
  * @param  string $query  sql query
  * @param  array  $params query params
  * @return array
  */
  public static function select($query,$params=null)
  {
    return DB::execute($query,$params,true);
  }

  /**
  * @param  string $query   sql query
  * @param  array  $params query params
  * @return stdClass or false(bool)   return first result
  */
  public static function getOne($query,$params=null)
  {
    return DB::mainDB()->getOne($query,$params);
  }

  /*
  * @param  string $query   sql query
  * @param  array  $params query params
  */
  public static function update($query,$params=null)
  {
    DB::execute($query,$params,false);
  }

  /*
  * @param  string $query   sql query
  * @param  array  $params query params
  */
  public static function insert($query,$params=null)
  {
    DB::execute($query,$params,false);
  }

  /*
  * @param  string $query   sql query
  * @param  array  $params query params
  */
  public static function delete($query,$params=null)
  {
    DB::execute($query,$params,false);
  }

  /*
  * can execute all sql query
  * @param  string $query   sql query
  * @param  array  $params query params
  * @param  bool  $return for receive result query
  */
  public static function execute($query,$params=null,$return=false)
  {
    return DB::mainDB()->execute($query,$params,$return);

  }

  public static function start_transaction()
  {
    DB::mainDB()->start_transaction();
  }

  public static function commit()
  {
    DB::mainDB()->commit();
  }

  public function rollback()
  {
    DB::mainDB()->rollback();
  }

  /**
  * get PDO Object
  * @return db
  */
  public static function getPDO()
  {
    return DB::mainDB()->getPDO();
  }

  /**
  * @return int last insert id
  */
  public static function lastInsertId()
  {
    return DB::mainDB()->lastInsertId();
  }

  /**
  * get First Config Key in $db_config
  * @return string
  */
  public static function getFirstConfigKey()
  {
    return key(DB::$db_config);
  }

  /**
  * Initialization of the database connection
  *
  * @param  [string,int] $key config key
  * @return PDO
  */
  private static function initOnceDB($key)
  {

    if (!isset( DB::$db_config[$key]['db'])) {
      $query=new query;
      DB::$db_config[$key]['db']=$query->set(DB::$db_config[$key]);
      return DB::$db_config[$key]['db'];
    }
    else {
      return DB::$db_config[$key]['db'];
    }
  }

  /**
  * get main (first) database pdo
  * @return PDO
  */
  public static function mainDB()
  {
    return DB::initOnceDB(DB::getFirstConfigKey());
  }

  public static function table($name)
  {
    $db= DB::mainDB();
    $table= new QueryBuilder($name,$db);
    return $table;
  }
}
