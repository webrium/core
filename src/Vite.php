<?php
namespace Webrium;


/**
 * Vite Asset Helper Class
 * * This class is responsible for managing Vite configuration and generating the correct 
 * <script> and <link> tags based on whether the application is running in 
 * Development mode (Vite server running) or Production mode (built files).
 */

class Vite
{
    protected const DEFAULT_HOST = 'localhost';
    protected const DEFAULT_PORT = 5173;
    
    // Path to the manifest file relative to this PHP file (adjust the path according to your file location)
    // Assuming this file is in a folder like app/Helpers and should reach public/build
    protected const MANIFEST_PATH = __DIR__ . '/../../public/build/.vite/manifest.json';
    
    // The entry point must exactly match what you set in vite.config.js > rollupOptions > input
    protected const DEFAULT_ENTRY_POINT = 'resources/js/app.js';
    
    protected const DEV_CLIENT_SCRIPT = '@vite/client';
    
    // Base URL for built files in production
    protected const PRODUCTION_ASSET_BASE_PATH = '/build/';

    protected static ?Vite $instance = null;
    protected string $host;
    protected int $port;
    protected bool $isDev = false;

    protected function __construct()
    {
        $this->host = self::DEFAULT_HOST;
        $this->port = self::DEFAULT_PORT;
        $this->checkDevStatus();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function assets(string $entryPoint = self::DEFAULT_ENTRY_POINT): string
    {
        if ($this->isDev) {
            return $this->renderDevTags($entryPoint);
        }
        return $this->renderProductionTags($entryPoint);
    }

    protected function checkDevStatus(): void
    {
        // Check if the Vite port is open
        $handle = @fsockopen($this->host, $this->port, $errno, $errstr, 0.1);
        if ($handle) {
            $this->isDev = true;
            fclose($handle);
        } else {
            $this->isDev = false;
        }
    }

    protected function renderDevTags(string $entryPoint): string
    {
        $baseUrl = "http://{$this->host}:{$this->port}/";
        
        return sprintf(
            '<script type="module" src="%s%s"></script>' . PHP_EOL .
            '<script type="module" src="%s%s"></script>',
            $baseUrl, self::DEV_CLIENT_SCRIPT,
            $baseUrl, $entryPoint
        );
    }

    protected function renderProductionTags(string $entryPoint): string
    {
        $manifestPath = self::MANIFEST_PATH;

        if (!file_exists($manifestPath)) {
            return "";
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        
        // In the new manifest, keys are usually stored as resources/js/app.js
        if (!isset($manifest[$entryPoint])) {
            return "";
        }

        $entryData = $manifest[$entryPoint];
        $output = '';

        // 1. CSS file (if styles are extracted)
        if (isset($entryData['css'])) {
            foreach ($entryData['css'] as $cssFile) {
                $output .= sprintf(
                    '<link rel="stylesheet" href="%s%s">' . PHP_EOL,
                    self::PRODUCTION_ASSET_BASE_PATH,
                    $cssFile
                );
            }
        }
        
        // 2. Main JS file
        if (isset($entryData['file'])) {
            $output .= sprintf(
                '<script type="module" src="%s%s"></script>',
                self::PRODUCTION_ASSET_BASE_PATH,
                $entryData['file']
            );
        }

        return $output;
    }
}