<?php

namespace Webrium;

class Url
{
    private static $main = false;

    public static function doc_root()
    {
        return self::without_trailing_slash(self::server('DOCUMENT_ROOT'));
    }

    public static function scheme($full = false)
    {

        $isSecure = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $isSecure = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
            $isSecure = true;
        }

        if ($isSecure) {
            return "https".($full ? '://' : '');
        } else {
            return "http".($full ? '://' : '');
        }

    }

    public static function domain()
    {
        return $_SERVER['HTTP_HOST'];
    }

    public static function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public static function home()
    {
        return self::scheme(true).self::domain();
    }

    public static function main()
    {

        if (self::$main == false) {

            $doc   = self::doc_root();
            $index = App::rootPath();

            $pos = \strpos($index, $doc);

            if ($pos > 0) {
                $index = \substr($index, $pos);
            }

            $len = strlen($doc);

            self::$main = self::home().substr($index, $len);
        }

        return self::$main;
    }

    public static function get($path = '')
    {
        return self::main()."/$path";
    }

    public static function current($resParams = false)
    {
        return self::home().self::uri($resParams);
    }

    public static function uri($resParams = false)
    {
        $uri = self::server('REQUEST_URI');

        if(!$resParams) {
            $arr = explode('?', $uri);
            $uri = $arr[0];
        }

        return urldecode($uri);
    }

    public static function server_addr()
    {
        return self::server('SERVER_ADDR');
    }

    public static function remote_addr()
    {
        return self::server('REMOTE_ADDR');
    }

    public static function query()
    {
        return self::server('QUERY_STRING');
    }

    public static function full()
    {
        return self::current(true);
    }

    public static function current_array()
    {
        $scheme  = self::scheme(true);
        $current = self::current();
        return array_filter(explode('/', substr($current, (strpos($current, $scheme) + strlen($scheme)))));
    }

    public static function server($key = false)
    {
        if($key) {
            return $_SERVER[$key] ?? false;
        } elseif ($key == false) {
            return $_SERVER;
        }
    }


    public static function is($text)
    {
        $current = self::current();
        $url = self::get($text);

        $star = false;
        if (strpos($url, '/*') > -1) {
            $url = str_replace('*', '', $url);
            if (strpos($current, $url) > -1 && (strlen($current) > strlen($url))) {
                $star = true;
            } else {
                return false;
            }
        }


        if ($url == $current || $star) {
            return true;
        } else {
            return false;
        }
    }


    public static function remove_end_char($value)
    {
        return substr($value, 0, -1);
    }

    public static function has_trailing_slash($value)
    {
        if (substr($value, -1) == '/') {
            return true;
        }
        return false;
    }

    public static function without_trailing_slash($value)
    {
        if (self::has_trailing_slash($value)) {
            $value = self::remove_end_char($value);
        }
        return $value;
    }

    // https://webmasters.googleblog.com/2010/04/to-slash-or-not-to-slash.html
    public static function redirect_without_trailing_slash($current)
    {
        $current = explode('?', $current);
        $current[0] = self::without_trailing_slash($current[0]);
        $url = implode('?', $current);

        redirect($url, 301);
    }

    public static function ConfirmUrl()
    {
        $trailing_slash = self::has_trailing_slash(self::current());

        if ($trailing_slash && self::current() != self::main().'/') {
            Url::redirect_without_trailing_slash(self::current(true));
        }
    }

}
