<?php

namespace Webrium;


/**
 * Static helper facade for quick requests
 */
class Http
{
    /**
     * Make GET request
     *
     * @param string $url URL
     * @param array $query Query parameters
     * @return HttpResponse
     */
    public static function get(string $url, array $query = []): HttpResponse
    {
        return HttpClient::make()->get($url, $query);
    }

    /**
     * Make POST request
     *
     * @param string $url URL
     * @param mixed $data Request data
     * @return HttpResponse
     */
    public static function post(string $url, mixed $data = []): HttpResponse
    {
        return HttpClient::make()->post($url, $data);
    }

    /**
     * Make PUT request
     *
     * @param string $url URL
     * @param mixed $data Request data
     * @return HttpResponse
     */
    public static function put(string $url, mixed $data = []): HttpResponse
    {
        return HttpClient::make()->put($url, $data);
    }

    /**
     * Make PATCH request
     *
     * @param string $url URL
     * @param mixed $data Request data
     * @return HttpResponse
     */
    public static function patch(string $url, mixed $data = []): HttpResponse
    {
        return HttpClient::make()->patch($url, $data);
    }

    /**
     * Make DELETE request
     *
     * @param string $url URL
     * @param mixed $data Optional request data
     * @return HttpResponse
     */
    public static function delete(string $url, mixed $data = null): HttpResponse
    {
        return HttpClient::make()->delete($url, $data);
    }

    /**
     * Make JSON POST request
     *
     * @param string $url URL
     * @param array $data JSON data
     * @return HttpResponse
     */
    public static function json(string $url, array $data = []): HttpResponse
    {
        return HttpClient::make()->asJson('POST', $url, $data);
    }

    /**
     * Create new HTTP client instance
     *
     * @param string|null $baseUrl Optional base URL
     * @return HttpClient
     */
    public static function client(?string $baseUrl = null): HttpClient
    {
        return HttpClient::make($baseUrl);
    }

    /**
     * Create HTTP client with bearer token
     *
     * @param string $token Bearer token
     * @return HttpClient
     */
    public static function withToken(string $token): HttpClient
    {
        return HttpClient::make()->withToken($token);
    }

    /**
     * Create HTTP client with headers
     *
     * @param array $headers Headers
     * @return HttpClient
     */
    public static function withHeaders(array $headers): HttpClient
    {
        return HttpClient::make()->withHeaders($headers);
    }
}