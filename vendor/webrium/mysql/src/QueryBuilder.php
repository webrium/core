<?php
namespace webrium\mysql;

class QueryBuilder
{
  public $arr=[];
  public $arr_values=[];
  public $arr_params=[];
  public $values_count=0;

  public $tb_name;
  public $db;

  function __construct($tb_name,$db)
  {
    $this->tb_name=$tb_name;
    $this->db=$db;
  }

  public function insert($params=[])
  {
    $fields=[];
    $values=[];
    $tb_name=$this->getTableName();

    foreach ($params as $key => $value) {
      $fields[]="$key";
      $values[]="?";
      $this->arr_values[0][]=$value;
    }

    $fields_str=implode(',',$fields);
    $values_str=implode(',',$values);

    $query="INSERT INTO $tb_name ($fields_str) VALUES ($values_str)";
    $this->arr[0]=$query;
    $this->execute();
  }

  private function updateStr($query)
  {
    $tb_name=$this->getTableName();

    $query=" UPDATE $tb_name SET $query ";
    $this->arr[0]=$query;
    $this->execute();
  }

  public function update($params)
  {
    $tb_name=$this->getTableName();
    $fields=[];

    foreach ($params as $key => $value) {
      $fields[]="$key = ?";
      $this->arr_values[0][]=$value;
    }

    $fields=implode(',',$fields);

    $query=" UPDATE $tb_name SET $fields ";
    $this->arr[0]=$query;

    $this->execute();
  }

  public function delete()
  {
    $tb_name=$this->getTableName();

    $query=" DELETE FROM $tb_name ";
    $this->arr[0]=$query;

    $this->execute();
  }

  public function truncate()
  {
    $tb_name=$this->getTableName();
    $query=" TRUNCATE TABLE $tb_name ";
    $this->arr[0]=$query;

    $this->execute();
  }

  public function decrement($name,$value=1)
  {
    $this->updateStr("$name=($name-$value)");
    return $this;
  }

  public function increment($name,$value=1)
  {
    $this->updateStr("$name=($name+$value)");
    return $this;
  }

  public function execute()
  {
    $query=$this->getSql();
    $this->db->execute($query,$this->arr_params);
  }

  public function getTableName()
  {
    return '`'.$this->tb_name.'`';
  }


  public function getSql()
  {
    $arr=$this->arr;
    ksort($arr);
    $str='';
    foreach ($arr as $key => $value) {
      $str.=$value;
      if (isset($this->arr_values[$key])) {
        $this->arr_params=array_merge($this->arr_params,$this->arr_values[$key]);
      }
    }

    return $str;
  }


  //==================================
  //=======/ SELECT Functions \=======
  //==================================


  public function selectInit($select=true,$all=false,$from=false,$where=false)
  {

    if ($select) {
      $index=$this->SelectSyntaxIndex('SELECT');
      if (! isset($this->arr[$index])) {
        $this->arr[$index]="SELECT ";
      }
    }

    if ($all) {
      $index_all=$this->SelectSyntaxIndex('ALL');
      if (! isset($this->arr[$index_all])) {
        $this->arr[$index_all]=" * ";
      }
    }

    if ($from) {
      $index_from=$this->SelectSyntaxIndex('FROM');
      if (! isset($this->arr[$index_from]) ) {
        $this->arr[$index_from]=" FROM ".$this->getTableName().' ';
      }
    }

    if ($where) {
      $index_where=$this->SelectSyntaxIndex('WHERE');
      if ( ! isset($this->arr[$index_where]) ) {
        $this->arr[$index_where]=" WHERE ";
      }
    }
    return $this;
  }

  public function select($params=null)
  {
    $index=$this->SelectSyntaxIndex('ALL');

    if (is_string($params)) {
      $this->arr[$index]=" $params ";
    }
    elseif (is_array($params)) {
      $params=implode(',',$params);
      $this->arr[$index]=" $params ";
    }

    return $this;
  }

  public function where($name,$val1,$val2=null,$fl="AND")
  {
    $value='';
    $op='=';
    if ($val2!=null) {
      $op=$val1;
      $value=$val2;
    }
    else {
      $value=$val1;
    }

    $key=$this->getKeyAndSetValue($value,'WHERE_STR');

    $this->whereStr("$name $op $key ",$fl);
    return $this;
  }


  public function orWhere($name,$val1,$val2=null)
  {
    $this->where($name,$val1,$val2,"OR");
    return $this;
  }

  public function whereIn($name,array $params,$fl='AND',$in="IN")
  {
    $index=$this->SelectSyntaxIndex('WHERE_STR');

    $keys=[];

    foreach ($params as $k => $val) {
      $key=$this->getKeyAndSetValue($val,'WHERE_STR');
      $keys[]=$key;
    }

    $this->whereStr("`$name` $in( ".implode(',',$keys)." ) ",$fl);
    return $this;
  }

  public function whereNotIn($name,$val1)
  {
    $this->whereIn($name,$val1,'AND',"NOT IN");
    return $this;
  }

  public function orWhereIn($name,$val1)
  {
    $this->whereIn($name,$val1,'OR');
    return $this;
  }
  public function orWhereNotIn($name,$val1)
  {
    $this->whereIn($name,$val1,'OR','NOT IN');
    return $this;
  }

  public function whereBetween($name,$val1,$val2,$fl='AND')
  {
    $between1=$this->getKeyAndSetValue($val1,'WHERE_STR');
    $between2=$this->getKeyAndSetValue($val2,'WHERE_STR');

    $this->whereStr(" `$name` BETWEEN $between1 AND $between2 ",$fl);
    return $this;
  }

  public function orWhereBetween($name,$val1,$val2)
  {
    $this->whereBetween($name,$val1,$val2,'OR');
    return $this;
  }

  //###################[null]####################

  public function whereNull($name,$fl='AND')
  {
    $this->whereStr("`$name` IS NULL ",$fl);
    return $this;
  }

  public function whereNotNull($name,$fl='AND')
  {
    $this->whereStr("`$name` IS NOT NULL ",$fl);
    return $this;
  }

  public function orWhereNull($name)
  {
    $this->whereNull($name,'OR');
    return $this;
  }

  public function orWhereNotNull($name)
  {
    $this->whereNotNull($name,'OR');
    return $this;
  }

  //###################[dateTime]####################
  public function whereDate($name,$date)
  {
    $this->whereBetween($name,"$date 00:00:00","$date 23:59:59");
    return $this;
  }

  public function whereMonth($name,$month,$fl='AND')
  {
    $key=$this->getKeyAndSetValue($month,'WHERE_STR');
    $this->whereStr(" MONTH(`$name`) = $key ",$fl);
    return $this;
  }

  public function whereDay($name,$day,$fl='AND')
  {
    $key=$this->getKeyAndSetValue($day,'WHERE_STR');
    $this->whereStr(" DAY(`$name`) = $key ",$fl);
    return $this;
  }

  public function whereYear($name,$year,$fl='AND')
  {
    $key=$this->getKeyAndSetValue($year,'WHERE_STR');
    $this->whereStr(" YEAR(`$name`) = $key ",$fl);
    return $this;
  }

  public function whereTime($name,$time,$fl='AND')
  {
    $key=$this->getKeyAndSetValue($time,'WHERE_STR');
    $this->whereStr(" TIME(`$name`) = $key ",$fl);
    return $this;
  }

  public function whereColumn($val1,$val2,$val3=null,$fl='AND')
  {
    $value1='';
    $value2='';

    $op='=';
    if ($val3!=null) {
      $op=$val2;
      $value1=$val1;
      $value2=$val3;
    }
    else {
      $value1=$val1;
      $value2=$val2;
    }

    $this->whereStr(" `$value1` $op `$value2` ",$fl);
    return $this;
  }

  public function orWhereColumn()
  {
    $this->whereColumn($val1,$val2,$val3,'OR');
    return $this;
  }

  private $first_fl_able=true;
  private function whereStr($query,$fl="AND")
  {
    $this->selectInit(false,false,false,true);
    $index=$this->SelectSyntaxIndex('WHERE_STR');

    if (isset($this->arr[$index]) && $this->first_fl_able) {
      $this->arr[$index].=" $fl ";
    }
    else if(! $this->first_fl_able) {

      //Used for the function p
      $this->first_fl_able=true;
      $this->arr[$index]=str_replace('@fl',$fl ,$this->arr[$index]);
    }


    $this->arr[$index].=$query;
  }

  public function is($name)
  {
    $this->where($name,true);
    return $this;
  }

  public function orIs($name)
  {
    $this->orWhere($name,true);
    return $this;
  }

  /**
  * To write code in parentheses
  *
  * @param function
  * return $this
  */
  public function p($func)
  {
    $this->whereStr('@fl (','');
    $this->first_fl_able=false;

    $func($this);

    $this->whereStr(')','');

    return $this;
  }


  //==================================
  //=======/ ORDER Functions \========
  //==================================

  public function orderBy($name,$order='ASC')
  {
    $index=$this->SelectSyntaxIndex('ORDER_BY');
    $this->arr[$index]=" ORDER BY $name $order ";
    return $this;
  }

  public function inRandomOrder()
  {
    $this->orderBy($key,'RAND()');
    return $this;
  }

  //==================================
  //=======/ groupBy Functions \======
  //==================================

  public function groupBy($name)
  {
    $index=$this->SelectSyntaxIndex('GROUP_BY');
    $this->arr[$index]=" GROUP BY $name ";
    return $this;
  }

  //==================================
  //=======/ union Functions \========
  //==================================

  public function union($select)
  {
    $index=$this->SelectSyntaxIndex('UNION');
    $qdb=$select->selectInit(true,true,true);
    $q1=$qdb->getSql();
    $this->arr[$index]=" UNION $q1";

    foreach ($qdb->arr_values as $key => $value) {
      $this->arr_values[$key]=array_merge($this->arr_values[$key],$value);
    }
    return $this;
  }

  //==================================
  //=======/ limits Functions \=======
  //==================================

  public function limit($limit1,$limit2=null)
  {
    $index=$this->SelectSyntaxIndex('LIMIT');

    if ($limit2==null) {
      $this->arr[$index]=" LIMIT $limit1";
    }
    else {
      $this->arr[$index]=" LIMIT $limit1,$limit2 ";
    }

    return $this;
  }

  public function skip($skip)
  {
    $this->offset($skip);
    return $this;
  }

  public function offset($offset)
  {
    $index=$this->SelectSyntaxIndex('OFFSET');
    $this->arr[$index].=" OFFSET $offset";
    return $this;
  }

  public function take($take)
  {
    $this->limit($take);
    return $this;
  }

  public function having($name,$op,$val1)
  {
    $index=$this->SelectSyntaxIndex('HAVING');
    $this->arr[$index]=" HAVING COUNT($name) $op $val1";
    return $this;
  }

  public function duplicate($name,$count)
  {
    $this->groupBy($name)->having($name,">",$count);
    return $this;
  }

  //==================================
  //=======/ JOIN Functions \=========
  //==================================

  public function join($name,$rel_id,$joinType='INNER')
  {
    $index=$this->SelectSyntaxIndex('JOIN');
    $this->arr[$index].=" $joinType JOIN $name ON $rel_id ";
    return $this;
  }

  public function leftJoin($name,$rel_id){
    $this->join($name,$rel_id,'LEFT');
    return $this;
  }

  public function rightJoin($name,$rel_id){
    $this->join($name,$rel_id,'LEFT');
    return $this;
  }

  public function fullJoin($name,$rel_id){
    $this->join($name,$rel_id,'FULL OUTER');
    return $this;
  }


  private function getKeyAndSetValue($value,$indexKey)
  {
    $index=null;
    $index=$this->SelectSyntaxIndex($indexKey);

    $this->values_count++;
    $key="?";

    $this->arr_values[$index][]=$value;

    return $key;
  }


  public function latest($key='id')
  {
    $this->orderBy($key,'DESC');
    return $this;
  }

  public function oldest($key='id')
  {
    $this->orderBy($key,'ASC');
    return $this;
  }

  public function count($name='*')
  {
    $res= $this->select("count($name) as count")->first();
    return $this->getAutoOb($res,'count');
  }

  public function sum($name)
  {
    $res= $this->select("sum($name) as sum")->first();
    return $this->getAutoOb($res,'sum');
  }

  function getAutoOb($ob,$name)
  {
    if ($this->db->config['result_stdClass']) {
      return $ob->$name;
    }
    else {
      return $ob[$name];
    }
  }
  public function find($id)
  {
    return $this->where('id',$id)->first();
  }

  public function get()
  {
    $this->selectInit(true,true,true);
    $query= $this->getSql();
    return $this->db->execute($query,$this->arr_params,true);
  }

  public function first()
  {
    $this->selectInit(true,true,true);
    $this->limit(1);
    $query= $this->getSql();
    return $this->db->getOne($query,$this->arr_params,true);
  }

  public function SelectSyntaxIndex($key=null)
  {
    $arr=[
      'SELECT'=>1,
      'FIELDS'=>2,
      'ALL'=>3,
      'DISTINCT '=>4,
      'DISTINCTROW'=>5,
      'HIGH_PRIORITY'=>6,
      'STRAIGHT_JOIN'=>7,
      'FROM'=>8,
      'JOIN'=>9,
      'WHERE'=>10,
      'WHERE_STR'=>11,
      'GROUP_BY'=>12,
      'HAVING'=>13,
      'ORDER_BY'=>14,
      'LIMIT'=>15,
      'OFFSET'=>16,
      'UNION'=>17
    ];
    if ($key==null) {
      return $arr;
    }
    else {
      return $arr[$key];
    }
  }

}
