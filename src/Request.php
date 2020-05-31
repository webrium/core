<?php
namespace webrium\core;

class Request
{

  public static function post($url,$params=[])
  {
    return self::send($url,$params,true);
  }

  public static function get($url,$params=[])
  {
    return self::send($url,$params,false);
  }

  public static function send($url,$params=[],$post=false)
  {
    if (! $post){
      $url.="?" . http_build_query($params);
    }

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);

    if (! $post){
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    }
    else{
      curl_setopt($ch, CURLOPT_POST,1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    }

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result=curl_exec($ch);
    curl_close($ch);
    return $result;
  }

}