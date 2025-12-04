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
    // --- Default Configuration ---
    protected const DEFAULT_HOST = 'localhost';
    protected const DEFAULT_PORT = 5173;
    // Default path to the manifest file, relative to the helper file's location
    protected const DEFAULT_MANIFEST_PATH = __DIR__ . '/../../public/build/.vite/manifest.json';
    protected const DEFAULT_ENTRY_POINT = 'resources/js/app.js';
    protected const DEV_CLIENT_SCRIPT = '@vite/client';
    protected const PRODUCTION_ASSET_BASE_PATH = '/build/';

    // --- State and Configuration ---
    protected static ?Vite $instance = null;
    protected string $host;
    protected int $port;
    protected string $manifestPath;
    protected bool $isDev = false;

    /**
     * Constructor - Sets default values and checks the initial development status.
     */
    protected function __construct()
    {
        $this->host = self::DEFAULT_HOST;
        $this->port = self::DEFAULT_PORT;
        $this->manifestPath = self::DEFAULT_MANIFEST_PATH;

        // Check Dev/Prod status during object construction
        $this->checkDevStatus();
    }

    /**
     * Singleton implementation to ensure only one instance of the class exists.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // --- Setter Methods ---

    /**
     * Sets the host for the Vite development server.
     */
    public function setHost(string $host): self
    {
        $this->host = $host;
        $this->checkDevStatus(); // Re-check Dev status after host change
        return $this;
    }

    /**
     * Sets the port for the Vite development server.
     */
    public function setPort(int $port): self
    {
        $this->port = $port;
        $this->checkDevStatus(); // Re-check Dev status after port change
        return $this;
    }

    /**
     * Sets the absolute path to the production manifest file.
     */
    public function setManifestPath(string $path): self
    {
        $this->manifestPath = $path;
        return $this;
    }

    // --- Main Method for HTML Tag Generation ---

    /**
     * Generates the required <script> and <link> tags for Vite assets.
     * * @param string $entryPoint The main entry point file (e.g., resources/js/app.js)
     * @return string The generated HTML tags.
     */
    public function assets(string $entryPoint = self::DEFAULT_ENTRY_POINT): string
    {
        if ($this->isDev) {
            return $this->renderDevTags($entryPoint);
        }

        return $this->renderProductionTags($entryPoint);
    }
    
    // --- Internal Private Methods ---

    /**
     * Checks if the Vite development server is running using a socket connection.
     * Note: For better performance in production environments, use an ENV variable instead.
     */
    protected function checkDevStatus(): void
    {
        // Check using fsockopen
        $handle = @fsockopen($this->host, $this->port);
        if ($handle) {
            $this->isDev = true;
            fclose($handle);
        } else {
            $this->isDev = false;
        }
    }

    /**
     * Generates HTML tags for Development mode (connecting to the Vite server).
     */
    protected function renderDevTags(string $entryPoint): string
    {
        $baseUrl = "http://{$this->host}:{$this->port}/";
        $clientUrl = $baseUrl . self::DEV_CLIENT_SCRIPT;
        $entryUrl = $baseUrl . $entryPoint;

        return sprintf(
            '<script type="module" src="%s"></script>' . PHP_EOL .
            '<script type="module" src="%s"></script>',
            $clientUrl,
            $entryUrl
        );
    }

    /**
     * Generates HTML tags for Production mode (reading from the Manifest file).
     */
    protected function renderProductionTags(string $entryPoint): string
    {
        if (!file_exists($this->manifestPath)) {
            // Manifest not found (build probably not executed)
            error_log("Vite Manifest file not found at: {$this->manifestPath}");
            return '';
        }

        $manifest = json_decode(file_get_contents($this->manifestPath), true);
        
        if (!isset($manifest[$entryPoint])) {
            error_log("Vite entry point '{$entryPoint}' not found in manifest.");
            return '';
        }

        $entryData = $manifest[$entryPoint];
        $output = '';

        // 1. CSS file (if present)
        if (isset($entryData['css'])) {
            foreach ($entryData['css'] as $cssFile) {
                $output .= sprintf(
                    '<link rel="stylesheet" href="%s%s">' . PHP_EOL,
                    self::PRODUCTION_ASSET_BASE_PATH,
                    $cssFile
                );
            }
        }
        
        // 2. JS file (mandatory)
        $jsFile = $entryData['file'];
        $output .= sprintf(
            '<script type="module" src="%s%s"></script>',
            self::PRODUCTION_ASSET_BASE_PATH,
            $jsFile
        );

        return $output;
    }
}
