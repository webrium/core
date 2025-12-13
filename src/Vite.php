<?php
namespace Webrium;

/**
 * Vite Asset Helper Class
 * 
 * This class is responsible for managing Vite configuration and generating the correct 
 * <script> and <link> tags based on whether the application is running in 
 * Development mode (Vite server running) or Production mode (built files).
 */
class Vite
{
    protected const DEFAULT_HOST = 'localhost';
    protected const DEFAULT_PORT = 5173;
    
    // The entry point must exactly match what is set in vite.config.js
    protected const DEFAULT_ENTRY_POINT = 'resources/js/app.js';
    
    protected const DEV_CLIENT_SCRIPT = '@vite/client';
    
    // Base path for built files in production
    protected const PRODUCTION_ASSET_BASE_PATH = '/build/';

    protected static ?Vite $instance = null;
    protected string $host;
    protected int $port;
    protected bool $isDev = false;
    protected ?string $basePath = null;

    protected function __construct()
    {
        $this->host = self::DEFAULT_HOST;
        $this->port = self::DEFAULT_PORT;
        $this->detectBasePath();
        $this->checkDevStatus();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Automatic detection of the project base path
     */
    protected function detectBasePath(): void
    {
        // If DOCUMENT_ROOT is set, use it
        if (isset($_SERVER['DOCUMENT_ROOT']) && !empty($_SERVER['DOCUMENT_ROOT'])) {
            // DOCUMENT_ROOT usually points to public
            $this->basePath = dirname($_SERVER['DOCUMENT_ROOT']);
        } else {
            // Fallback: Use the current file's path
            // Assumption: This file is in app/Helpers or vendor/...
            $this->basePath = dirname(__DIR__, 2);
        }
    }

    /**
     * Manually set the project base path
     */
    public function setBasePath(string $path): self
    {
        $this->basePath = rtrim($path, '/\\');
        return $this;
    }

    /**
     * Get the manifest file path
     */
    protected function getManifestPath(): string
    {
        return $this->basePath . '/public/build/.vite/manifest.json';
    }

    /**
     * Generate HTML tags for assets
     */
    public function assets(string $entryPoint = self::DEFAULT_ENTRY_POINT): string
    {
        if ($this->isDev) {
            return $this->renderDevTags($entryPoint);
        }
        return $this->renderProductionTags($entryPoint);
    }

    /**
     * Check development server status
     */
    protected function checkDevStatus(): void
    {
        $handle = @fsockopen($this->host, $this->port, $errno, $errstr, 0.1);
        if ($handle) {
            $this->isDev = true;
            fclose($handle);
        } else {
            $this->isDev = false;
        }
    }

    /**
     * Generate tags for development mode
     */
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

    /**
     * Generate tags for production mode
     */
    protected function renderProductionTags(string $entryPoint): string
    {
        $manifestPath = $this->getManifestPath();

        if (!file_exists($manifestPath)) {
            // Debug: Show error if manifest not found
            if ($this->isDebugMode()) {
                return "<!-- Vite Error: Manifest not found at: {$manifestPath} -->";
            }
            return "";
        }

        $manifestContent = file_get_contents($manifestPath);
        $manifest = json_decode($manifestContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($this->isDebugMode()) {
                return "<!-- Vite Error: Invalid manifest JSON -->";
            }
            return "";
        }

        // Check if entry point exists in manifest
        if (!isset($manifest[$entryPoint])) {
            if ($this->isDebugMode()) {
                $availableKeys = implode(', ', array_keys($manifest));
                return "<!-- Vite Error: Entry '{$entryPoint}' not found. Available: {$availableKeys} -->";
            }
            return "";
        }

        $entryData = $manifest[$entryPoint];
        $output = '';

        // 1. CSS files (if styles are extracted)
        if (isset($entryData['css']) && is_array($entryData['css'])) {
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

    /**
     * Check debug mode
     */
    protected function isDebugMode(): bool
    {
        // You can replace this with an environment variable or your framework settings
        return defined('DEBUG_MODE') && DEBUG_MODE === true;
    }

    /**
     * Get debug information
     */
    public function debug(): array
    {
        $manifestPath = $this->getManifestPath();
        
        return [
            'isDev' => $this->isDev,
            'host' => $this->host,
            'port' => $this->port,
            'basePath' => $this->basePath,
            'manifestPath' => $manifestPath,
            'manifestExists' => file_exists($manifestPath),
            'manifestContent' => file_exists($manifestPath) 
                ? json_decode(file_get_contents($manifestPath), true) 
                : null,
        ];
    }

    /**
     * Set host and port for development server
     */
    public function setDevServer(string $host, int $port): self
    {
        $this->host = $host;
        $this->port = $port;
        $this->checkDevStatus();
        return $this;
    }

    /**
     * Check if in development mode
     */
    public function isDevelopment(): bool
    {
        return $this->isDev;
    }
}