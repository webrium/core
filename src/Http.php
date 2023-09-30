<?php

namespace webrium;

class Http
{
    /**
     * send post request
     *
     * @param  string $url
     * @param  array  $params
     * @param  callable|null  $customCurl
     * @return string
     */
    public static function post($url, $params = [], $customCurl = null)
    {
        return self::send($url, $params, true, $customCurl);
    }

    /**
     * send get request
     *
     * @param  string $url
     * @param  array  $params
     * @param  callable|null  $customCurl
     * @return string
     */
    public static function get($url, $params = [], $customCurl = null)
    {
        return self::send($url, $params, false, $customCurl);
    }

    /**
     * send json request
     *
     * @param  string $url
     * @param  array  $params
     * @return string
     */
    public static function json($url, $params = [])
    {
        return self::send($url, $params, true, function ($ch) use ($params) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        });
    }


    /**
     * send request
     *
     * @param  string  $url
     * @param  array   $params
     * @param  boolean $post  true for send post method
     * @param  callable|null  $customCurl
     * @return string
     */
    private static function send($url, $params = [], $post = false, $customCurl = null)
    {
        if (!$post) {
            $url .= "?" . http_build_query($params);
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

        if (!$post) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        } else {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($customCurl) {
            $customCurl($ch);
        }

        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


    /**
     * get client ip
     *
     * @return string
     */
    public static function ip()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) { //check ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { //to check ip is pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

}
