<?php
namespace webrium\mysql;

use webrium\mysql\QueryBuilder;


class Query
{

  private $db;
  private $isConnect=false;
  public $config=null;

  public function set($config)
  {
    $db=new \PDO($config['driver'].":host=".$config['db_host'].':'.$config['db_host_port'].";dbname=".$config['db_name'].";charset=".$config['charset'],$config['username'],$config['password']);
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $this->setSelectResultType(! $config['result_stdClass']);
    $this->db=$db;
    $this->config=$config;
    $this->isConnect=true;
    return $this;
  }

  public function getPDO()
  {
    return $this->db;
  }

  public function table($name)
  {
    $table= new queryBuilder($name,$this);
    return $table;
  }

  public function lastInsertId()
  {
    return $this->getPDO()->lastInsertId();
  }

  public function start_transaction()
  {
    $this->execute('START TRANSACTION;');
  }

  public function commit()
  {
    $this->execute('COMMIT;');
  }

  public function rollback()
  {
    $this->execute('ROLLBACK;');
  }

  public function execute($query,$params=null,$return=false){

    if ($params==null) {
      $stmt = $this->db->query($query);
    }
    else {
      $stmt=$this->db->prepare($query);
      $stmt->execute($params);
    }

    if($return){
      return $stmt->fetchAll($this->getType);
    }
  }
  public function select($query,$params=null){
    return self::execute($query,$params,true);
  }
  public function insert($query,$params=null){
    $this->execute($query,$params,false);
  }
  public function update($query,$params=null){
    $this->execute($query,$params,false);
  }
  public function delete($query,$params=null){
    $this->execute($query,$params,false);
  }

  public function getOne($query,$params=null){
    $result = $this->execute($query,$params,true);
    if (is_array($result) && count($result)>0) {
      return $result[0];
    }
    else{
      return false;
    }
  }


  public function setSelectResultType($getArray)
  {
    if ($getArray) {
      $this->getType=\PDO::FETCH_ASSOC;
    }
    else {
      $this->getType=\PDO::FETCH_CLASS;
    }
  }

  public function cleenBackup($dir='')
  {
    File::delete_dir($this->database_path("/backup$dir"));

  }
  public function database_path($dir=''){
    return app_path("/Other/framework/database$dir");
  }

  public function backup($savePath=null,$table=null)
  {
    set_time_limit(0);
    $date=date('Y-m-d_H_i_s');
    $dm=date('Y_m_d');
    $dir=$this->database_path("/backup/$dm/");

    mkdir($dir,0777, true);

    $dbhost=$this->config['db_host'];
    $dbname=$this->config['db_name'];
    $dbuser=$this->config['username'];
    $dbpass=$this->config['password'];

    $file_path='';



    if ($table==null) {

      if ($savePath==null) {
        $savePath=$dbname.'_'.$date.".sql";
        $file_path=$dir.$savePath;
      }
      else {
        $file_path=$dir.$savePath;
      }

      $command = "mysqldump --opt -h$dbhost -u$dbuser -p$dbpass $dbname > $file_path";
    }
    else {

      if ($savePath==null) {
        $savePath=$dbname.'_'.$table.'_'.$date.".sql";
        $file_path=$dir.$savePath;
      }
      else {
        $file_path=$dir.$savePath;
      }

      $command = "mysqldump --opt -h$dbhost -u$dbuser -p$dbpass $dbname $table > $file_path";
    }

    system($command);
    return ['ok'=>true,'name'=>$name,'path'=>$dir];
  }

}
