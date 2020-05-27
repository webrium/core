<?php
namespace webrium\core;

class Debug
{
  public function displayErrors($status)
  {
    if ($status) {
      ini_set('display_errors', $status);
      ini_set('display_startup_errors', $status);
      error_reporting(E_ALL);
    }
  }
}
