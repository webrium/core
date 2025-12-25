<?php

namespace Webrium;

/**
 * Modern HTTP Client
 * Fluent API for making HTTP requests with cURL
 * 
 * @package Webrium
 * @requires PHP 8.0+
 */
class HttpClient
{
    private string $url = '';
    private string $method = 'GET';
    private array $headers = [];
    private mixed $body = null;
    private array $options = [];
    private array $queryParams = [];
    private ?int $timeout = 30;
    private bool $verifySSL = true;
    private bool $followRedirects = true;
    private int $maxRedirects = 5;
    private ?string $userAgent = null;
    private array $middleware = [];
    
    /**
     * Create new HTTP client instance
     *
     * @param string|null $baseUrl Optional base URL
     */
    public function __construct(?string $baseUrl = null)
    {
        if ($baseUrl !== null) {
            $this->url = rtrim($baseUrl, '/');
        }
        
        $this->userAgent = 'Webrium-HttpClient/1.0';
    }

    /**
     * Create a new HTTP client instance
     *
     * @param string|null $baseUrl Optional base URL
     * @return self
     */
    public static function make(?string $baseUrl = null): self
    {
        return new self($baseUrl);
    }

    /**
     * Set request URL
     *
     * @param string $url Request URL
     * @return self
     */
    public function url(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Add query parameters
     *
     * @param array $params Query parameters
     * @return self
     */
    public function withQuery(array $params): self
    {
        $this->queryParams = array_merge($this->queryParams, $params);
        return $this;
    }

    /**
     * Set request headers
     *
     * @param array $headers Headers as key-value pairs
     * @return self
     */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $key => $value) {
            $this->headers[$key] = $value;
        }
        return $this;
    }

    /**
     * Set a single header
     *
     * @param string $key Header name
     * @param string $value Header value
     * @return self
     */
    public function withHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Set authorization bearer token
     *
     * @param string $token Bearer token
     * @return self
     */
    public function withToken(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    /**
     * Set basic authentication
     *
     * @param string $username Username
     * @param string $password Password
     * @return self
     */
    public function withBasicAuth(string $username, string $password): self
    {
        $credentials = base64_encode($username . ':' . $password);
        return $this->withHeader('Authorization', 'Basic ' . $credentials);
    }

    /**
     * Set request body
     *
     * @param mixed $body Request body
     * @return self
     */
    public function withBody(mixed $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Set timeout in seconds
     *
     * @param int $seconds Timeout in seconds
     * @return self
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Disable SSL verification (not recommended for production)
     *
     * @param bool $verify Verify SSL certificate
     * @return self
     */
    public function withoutVerifying(bool $verify = false): self
    {
        $this->verifySSL = $verify;
        return $this;
    }

    /**
     * Set whether to follow redirects
     *
     * @param bool $follow Follow redirects
     * @param int $maxRedirects Maximum number of redirects
     * @return self
     */
    public function withRedirects(bool $follow = true, int $maxRedirects = 5): self
    {
        $this->followRedirects = $follow;
        $this->maxRedirects = $maxRedirects;
        return $this;
    }

    /**
     * Set user agent
     *
     * @param string $userAgent User agent string
     * @return self
     */
    public function withUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Set custom cURL options
     *
     * @param array $options cURL options
     * @return self
     */
    public function withOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * Add middleware to modify request/response
     *
     * @param callable $callback Middleware callback
     * @return self
     */
    public function middleware(callable $callback): self
    {
        $this->middleware[] = $callback;
        return $this;
    }

    /**
     * Send GET request
     *
     * @param string|null $url Optional URL
     * @param array $query Optional query parameters
     * @return HttpResponse
     */
    public function get(?string $url = null, array $query = []): HttpResponse
    {
        if ($url !== null) {
            $this->url($url);
        }
        
        if (!empty($query)) {
            $this->withQuery($query);
        }
        
        return $this->send('GET');
    }

    /**
     * Send POST request
     *
     * @param string|null $url Optional URL
     * @param mixed $data Request data
     * @return HttpResponse
     */
    public function post(?string $url = null, mixed $data = null): HttpResponse
    {
        if ($url !== null) {
            $this->url($url);
        }
        
        if ($data !== null) {
            $this->withBody($data);
        }
        
        return $this->send('POST');
    }

    /**
     * Send PUT request
     *
     * @param string|null $url Optional URL
     * @param mixed $data Request data
     * @return HttpResponse
     */
    public function put(?string $url = null, mixed $data = null): HttpResponse
    {
        if ($url !== null) {
            $this->url($url);
        }
        
        if ($data !== null) {
            $this->withBody($data);
        }
        
        return $this->send('PUT');
    }

    /**
     * Send PATCH request
     *
     * @param string|null $url Optional URL
     * @param mixed $data Request data
     * @return HttpResponse
     */
    public function patch(?string $url = null, mixed $data = null): HttpResponse
    {
        if ($url !== null) {
            $this->url($url);
        }
        
        if ($data !== null) {
            $this->withBody($data);
        }
        
        return $this->send('PATCH');
    }

    /**
     * Send DELETE request
     *
     * @param string|null $url Optional URL
     * @param mixed $data Optional request data
     * @return HttpResponse
     */
    public function delete(?string $url = null, mixed $data = null): HttpResponse
    {
        if ($url !== null) {
            $this->url($url);
        }
        
        if ($data !== null) {
            $this->withBody($data);
        }
        
        return $this->send('DELETE');
    }

    /**
     * Send JSON request
     *
     * @param string $method HTTP method
     * @param string|null $url Optional URL
     * @param array $data JSON data
     * @return HttpResponse
     */
    public function asJson(string $method = 'POST', ?string $url = null, array $data = []): HttpResponse
    {
        if ($url !== null) {
            $this->url($url);
        }
        
        $this->withHeader('Content-Type', 'application/json');
        $this->withHeader('Accept', 'application/json');
        $this->withBody(json_encode($data));
        
        return $this->send($method);
    }

    /**
     * Send form data request
     *
     * @param string|null $url Optional URL
     * @param array $data Form data
     * @return HttpResponse
     */
    public function asForm(?string $url = null, array $data = []): HttpResponse
    {
        if ($url !== null) {
            $this->url($url);
        }
        
        $this->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->withBody(http_build_query($data));
        
        return $this->send('POST');
    }

    /**
     * Send multipart form data (file upload)
     *
     * @param string|null $url Optional URL
     * @param array $data Multipart data
     * @return HttpResponse
     */
    public function asMultipart(?string $url = null, array $data = []): HttpResponse
    {
        if ($url !== null) {
            $this->url($url);
        }
        
        $this->withBody($data);
        
        return $this->send('POST');
    }

    /**
     * Send the HTTP request
     *
     * @param string $method HTTP method
     * @return HttpResponse
     * @throws \RuntimeException
     */
    private function send(string $method): HttpResponse
    {
        $this->method = strtoupper($method);
        
        // Build full URL with query parameters
        $url = $this->buildUrl();
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set basic options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
        
        // Set timeout
        if ($this->timeout !== null) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        }
        
        // SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySSL ? 2 : 0);
        
        // Follow redirects
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->followRedirects);
        curl_setopt($ch, CURLOPT_MAXREDIRS, $this->maxRedirects);
        
        // Set user agent
        if ($this->userAgent !== null) {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        }
        
        // Set headers
        if (!empty($this->headers)) {
            $headerLines = [];
            foreach ($this->headers as $key => $value) {
                $headerLines[] = "$key: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        }
        
        // Set request body
        if ($this->body !== null && in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
        }
        
        // Apply custom options
        foreach ($this->options as $option => $value) {
            curl_setopt($ch, $option, $value);
        }
        
        // Apply middleware before sending
        foreach ($this->middleware as $middleware) {
            $ch = $middleware($ch, $this) ?? $ch;
        }
        
        // Execute request
        $response = curl_exec($ch);
        
        // Handle cURL errors
        if ($response === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            throw new \RuntimeException("cURL Error ($errno): $error");
        }
        
        // Get response info
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        // Parse response
        return $this->parseResponse($response, $info);
    }

    /**
     * Build full URL with query parameters
     *
     * @return string Full URL
     */
    private function buildUrl(): string
    {
        $url = $this->url;
        
        if (!empty($this->queryParams)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($this->queryParams);
        }
        
        return $url;
    }

    /**
     * Parse cURL response
     *
     * @param string $response Raw response
     * @param array $info cURL info
     * @return HttpResponse
     */
    private function parseResponse(string $response, array $info): HttpResponse
    {
        $headerSize = $info['header_size'];
        $headerContent = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        // Parse headers
        $headers = [];
        $headerLines = explode("\r\n", trim($headerContent));
        
        foreach ($headerLines as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        
        return new HttpResponse(
            statusCode: $info['http_code'],
            headers: $headers,
            body: $body,
            info: $info
        );
    }

    /**
     * Reset client state for reuse
     *
     * @return self
     */
    public function reset(): self
    {
        $this->method = 'GET';
        $this->headers = [];
        $this->body = null;
        $this->queryParams = [];
        $this->middleware = [];
        
        return $this;
    }
}

/**
 * HTTP Response wrapper
 */
class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly array $headers,
        public readonly string $body,
        public readonly array $info
    ) {}

    /**
     * Check if response is successful (2xx)
     *
     * @return bool
     */
    public function successful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if response is OK (200)
     *
     * @return bool
     */
    public function ok(): bool
    {
        return $this->statusCode === 200;
    }

    /**
     * Check if response is redirect (3xx)
     *
     * @return bool
     */
    public function redirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Check if response is client error (4xx)
     *
     * @return bool
     */
    public function clientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if response is server error (5xx)
     *
     * @return bool
     */
    public function serverError(): bool
    {
        return $this->statusCode >= 500;
    }

    /**
     * Check if response failed (4xx or 5xx)
     *
     * @return bool
     */
    public function failed(): bool
    {
        return $this->clientError() || $this->serverError();
    }

    /**
     * Get response body as string
     *
     * @return string
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Parse JSON response
     *
     * @param bool $assoc Return associative array
     * @return mixed
     */
    public function json(bool $assoc = true): mixed
    {
        return json_decode($this->body, $assoc);
    }

    /**
     * Get response header
     *
     * @param string $key Header name
     * @param mixed $default Default value
     * @return mixed
     */
    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[$key] ?? $default;
    }

    /**
     * Get all headers
     *
     * @return array
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Get status code
     *
     * @return int
     */
    public function status(): int
    {
        return $this->statusCode;
    }

    /**
     * Throw exception if request failed
     *
     * @return self
     * @throws \RuntimeException
     */
    public function throw(): self
    {
        if ($this->failed()) {
            throw new \RuntimeException(
                "HTTP request failed with status {$this->statusCode}: {$this->body}"
            );
        }
        
        return $this;
    }

    /**
     * Execute callback if response is successful
     *
     * @param callable $callback Callback function
     * @return self
     */
    public function onSuccess(callable $callback): self
    {
        if ($this->successful()) {
            $callback($this);
        }
        
        return $this;
    }

    /**
     * Execute callback if response failed
     *
     * @param callable $callback Callback function
     * @return self
     */
    public function onError(callable $callback): self
    {
        if ($this->failed()) {
            $callback($this);
        }
        
        return $this;
    }

    /**
     * Get cURL info
     *
     * @param string|null $key Specific info key
     * @return mixed
     */
    public function info(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->info;
        }
        
        return $this->info[$key] ?? null;
    }

    /**
     * Convert response to string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->body;
    }
}
