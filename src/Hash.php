<?php
namespace webrium\core;

class Hash{

  public static function make($text='')
  {
    return password_hash($text, PASSWORD_DEFAULT);
  }

  public static function check($password,$hash)
  {
    return password_verify($password,$hash);
  }

}
