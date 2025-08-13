<?php

namespace webrium;

class Header
{

    public static function all()
    {
        return getallheaders();
    }



    /** 
     * Get header Authorization
     */
    public static function getAuthorizationHeader()
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));

            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    /**
     * get access token from header
     */
    public static function getBearerToken()
    {
        $headers = self::getAuthorizationHeader();

        if (!empty($headers)) {
            return substr($headers, 7);
        }

        return false;
    }


    /**
     * Set CORS headers
     * @param string $allowed_origin The origin that is allowed to access the resource. Default is "*".
     * @return void
     */
    public static function cors($allowed_origin = "*")
    {
        header("Access-Control-Allow-Origin: $allowed_origin");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Credentials: true");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204); 
            exit();
        }
    }
}
