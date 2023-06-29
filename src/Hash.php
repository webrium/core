<?php

namespace Webrium;

class Hash
{

  /**
   * Hash a text using the given algorithm.
   *
   * @param string $text The text to be hashed.
   * @param int $algorithm [optional] The hashing algorithm to be used. Defaults to PASSWORD_DEFAULT.
   * @return string The hashed text.
   */
  public static function make($text = '', $algorithm = PASSWORD_DEFAULT)
  {
    return password_hash($text, $algorithm);
  }

  /**
   * Check if a password matches a certain hash.
   *
   * @param string $password The password to be checked.
   * @param string $hash The hash to be compared against.
   * @return bool Returns true if the password matches the hash, false otherwise.
   */
  public static function check($password, $hash)
  {
    return password_verify($password, $hash);
  }
}
