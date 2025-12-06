<?php

namespace Webrium;

use Webrium\App;

/**
 * Directory Manager Class
 * 
 * A comprehensive directory structure management utility for framework applications.
 * Provides methods to define, create, validate, and manage application directories.
 * 
 * Features:
 * - Directory registration and retrieval
 * - Automatic directory creation with proper permissions
 * - Directory validation and health checks
 * - Path resolution (relative to root)
 * - Recursive directory operations
 * - Default framework structure initialization
 * - Directory existence checks
 * - Bulk directory operations
 * 
 * @package Webrium
 * @version 2.0.0
 */
class Directory
{
    /**
     * Registered directory paths
     *
     * @var array
     */
    private static $directories = [];

    /**
     * Default directory permissions
     *
     * @var int
     */
    private const DEFAULT_PERMISSIONS = 0755;

    /**
     * Cache for resolved paths
     *
     * @var array
     */
    private static $pathCache = [];

    /**
     * Register a directory with a name
     *
     * @param string $name The directory identifier
     * @param string $path The relative path from root
     * @return void
     */
    public static function set(string $name, string $path): void
    {
        self::$directories[$name] = rtrim($path, '/\\');
        
        // Clear cached path for this directory
        if (isset(self::$pathCache[$name])) {
            unset(self::$pathCache[$name]);
        }
    }

    /**
     * Register multiple directories at once
     *
     * @param array $directories Associative array of name => path pairs
     * @return void
     */
    public static function setMultiple(array $directories): void
    {
        foreach ($directories as $name => $path) {
            self::set($name, $path);
        }
    }

    /**
     * Get the relative path of a registered directory
     *
     * @param string $name The directory identifier
     * @return string|null The relative path, or null if not found
     */
    public static function get(string $name): ?string
    {
        return self::$directories[$name] ?? null;
    }

    /**
     * Get the absolute path of a registered directory
     *
     * @param string $name The directory identifier
     * @param string $append Optional path to append
     * @return string|null The absolute path, or null if not found
     */
    public static function path(string $name, string $append = ''): ?string
    {
        if (!isset(self::$directories[$name])) {
            return null;
        }

        // Use cache for base path
        if (!isset(self::$pathCache[$name])) {
            self::$pathCache[$name] = App::getRootPath() . '/' . self::$directories[$name];
        }

        $path = self::$pathCache[$name];

        if ($append !== '') {
            $path .= '/' . ltrim($append, '/\\');
        }

        return $path;
    }

    /**
     * Get all registered directories
     *
     * @return array Associative array of all directories
     */
    public static function all(): array
    {
        return self::$directories;
    }

    /**
     * Get all absolute paths
     *
     * @return array Associative array of name => absolute path
     */
    public static function allPaths(): array
    {
        $paths = [];
        foreach (self::$directories as $name => $relativePath) {
            $paths[$name] = self::path($name);
        }
        return $paths;
    }

    /**
     * Check if a directory is registered
     *
     * @param string $name The directory identifier
     * @return bool True if registered, false otherwise
     */
    public static function has(string $name): bool
    {
        return isset(self::$directories[$name]);
    }

    /**
     * Check if a registered directory exists on filesystem
     *
     * @param string $name The directory identifier
     * @return bool True if exists, false otherwise
     */
    public static function exists(string $name): bool
    {
        $path = self::path($name);
        return $path !== null && is_dir($path);
    }

    /**
     * Remove a directory from registry (does not delete from filesystem)
     *
     * @param string $name The directory identifier
     * @return bool True if removed, false if not found
     */
    public static function forget(string $name): bool
    {
        if (isset(self::$directories[$name])) {
            unset(self::$directories[$name]);
            unset(self::$pathCache[$name]);
            return true;
        }
        return false;
    }

    /**
     * Clear all registered directories
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$directories = [];
        self::$pathCache = [];
    }

    /**
     * Create a directory if it doesn't exist
     *
     * @param string $path The absolute path or directory name
     * @param int $permissions The directory permissions (default: 0755)
     * @param bool $recursive Create parent directories if needed
     * @return bool True on success, false on failure
     */
    public static function make(string $path, int $permissions = self::DEFAULT_PERMISSIONS, bool $recursive = true): bool
    {
        // If $path is a registered name, get its absolute path
        if (self::has($path)) {
            $path = self::path($path);
        }

        if (is_dir($path)) {
            return true;
        }

        return @mkdir($path, $permissions, $recursive);
    }

    /**
     * Create all registered directories
     *
     * @param int $permissions The directory permissions (default: 0755)
     * @return array Array of created directory names
     */
    public static function makeAll(int $permissions = self::DEFAULT_PERMISSIONS): array
    {
        $created = [];

        foreach (self::all() as $name => $relativePath) {
            $path = self::path($name);
            
            if (self::make($path, $permissions)) {
                $created[] = $name;
            }
        }

        return $created;
    }

    /**
     * Alias for makeAll() for backward compatibility
     *
     * @return array Array of created directory names
     */
    public static function makes(): array
    {
        return self::makeAll();
    }

    /**
     * Validate that all registered directories exist
     *
     * @return bool True if all exist, false if any are missing
     */
    public static function validate(): bool
    {
        foreach (self::all() as $name => $relativePath) {
            if (!self::exists($name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing directories (registered but not existing)
     *
     * @return array Array of missing directory names
     */
    public static function getMissing(): array
    {
        $missing = [];

        foreach (self::all() as $name => $relativePath) {
            if (!self::exists($name)) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * Get existing directories
     *
     * @return array Array of existing directory names
     */
    public static function getExisting(): array
    {
        $existing = [];

        foreach (self::all() as $name => $relativePath) {
            if (self::exists($name)) {
                $existing[] = $name;
            }
        }

        return $existing;
    }

    /**
     * Check if a directory is writable
     *
     * @param string $name The directory identifier
     * @return bool True if writable, false otherwise
     */
    public static function isWritable(string $name): bool
    {
        $path = self::path($name);
        return $path !== null && is_writable($path);
    }

    /**
     * Check if a directory is readable
     *
     * @param string $name The directory identifier
     * @return bool True if readable, false otherwise
     */
    public static function isReadable(string $name): bool
    {
        $path = self::path($name);
        return $path !== null && is_readable($path);
    }

    /**
     * Get directory size in bytes
     *
     * @param string $name The directory identifier
     * @return int|false The size in bytes, or false on failure
     */
    public static function size(string $name)
    {
        $path = self::path($name);
        
        if ($path === null || !is_dir($path)) {
            return false;
        }

        return self::calculateDirectorySize($path);
    }

    /**
     * Calculate the total size of a directory recursively
     *
     * @param string $path The absolute path
     * @return int The size in bytes
     */
    private static function calculateDirectorySize(string $path): int
    {
        $size = 0;
        
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        ) as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    /**
     * Get human-readable directory size
     *
     * @param string $name The directory identifier
     * @param int $precision Decimal precision (default: 2)
     * @return string|false Human-readable size, or false on failure
     */
    public static function humanSize(string $name, int $precision = 2)
    {
        $bytes = self::size($name);
        
        if ($bytes === false) {
            return false;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Copy a directory to another location
     *
     * @param string $source The source directory name or path
     * @param string $destination The destination path
     * @param bool $overwrite Overwrite existing files
     * @return bool True on success, false on failure
     */
    public static function copy(string $source, string $destination, bool $overwrite = false): bool
    {
        $sourcePath = self::has($source) ? self::path($source) : $source;

        if (!is_dir($sourcePath)) {
            return false;
        }

        if (!is_dir($destination)) {
            mkdir($destination, self::DEFAULT_PERMISSIONS, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . '/' . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, self::DEFAULT_PERMISSIONS, true);
                }
            } else {
                if ($overwrite || !file_exists($target)) {
                    copy($item->getPathname(), $target);
                }
            }
        }

        return true;
    }

    /**
     * Delete a directory and its contents
     *
     * @param string $name The directory identifier or absolute path
     * @return bool True on success, false on failure
     */
    public static function delete(string $name): bool
    {
        $path = self::has($name) ? self::path($name) : $name;

        if (!is_dir($path)) {
            return false;
        }

        return self::recursiveDelete($path);
    }

    /**
     * Recursively delete a directory
     *
     * @param string $path The absolute path
     * @return bool True on success, false on failure
     */
    private static function recursiveDelete(string $path): bool
    {
        $files = array_diff(scandir($path), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $path . '/' . $file;
            
            if (is_dir($filePath)) {
                self::recursiveDelete($filePath);
            } else {
                unlink($filePath);
            }
        }

        return rmdir($path);
    }

    /**
     * Empty a directory (delete contents but keep directory)
     *
     * @param string $name The directory identifier
     * @return bool True on success, false on failure
     */
    public static function empty(string $name): bool
    {
        $path = self::path($name);

        if ($path === null || !is_dir($path)) {
            return false;
        }

        $files = array_diff(scandir($path), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $path . '/' . $file;
            
            if (is_dir($filePath)) {
                self::recursiveDelete($filePath);
            } else {
                unlink($filePath);
            }
        }

        return true;
    }

    /**
     * Get directory permissions
     *
     * @param string $name The directory identifier
     * @return int|false The permissions, or false on failure
     */
    public static function permissions(string $name)
    {
        $path = self::path($name);
        return ($path !== null && is_dir($path)) ? fileperms($path) : false;
    }

    /**
     * Set directory permissions
     *
     * @param string $name The directory identifier
     * @param int $permissions The permissions to set
     * @return bool True on success, false on failure
     */
    public static function chmod(string $name, int $permissions): bool
    {
        $path = self::path($name);
        return ($path !== null && is_dir($path)) ? chmod($path, $permissions) : false;
    }

    /**
     * Initialize the default framework directory structure
     *
     * @return void
     */
    public static function initDefaultStructure(): void
    {
        self::setMultiple([
            // Application directories
            'app' => 'app',
            'controllers' => 'app/Controllers',
            'models' => 'app/Models',
            'views' => 'app/Views',
            'routes' => 'app/Routes',
            'config' => 'app/Config',
            'middleware' => 'app/Middleware',
            'helpers' => 'app/Helpers',
            'services' => 'app/Services',

            // Storage directories
            'storage' => 'storage',
            'storage_app' => 'storage/App',
            'sessions' => 'storage/Framework/Sessions',
            'cache' => 'storage/Framework/Cache',
            'render_views' => 'storage/Framework/Views/rendered',
            'static_views' => 'storage/Framework/Views/static',

            // Logs and languages
            'logs' => 'storage/Logs',
            'langs' => 'storage/Langs',

            // Public directory
            'public' => 'public',
            'assets' => 'public/assets',
            'uploads' => 'public/uploads',
        ]);
    }

    /**
     * Get a directory structure as a tree
     *
     * @param string $name The directory identifier
     * @param int $maxDepth Maximum depth to traverse (-1 for unlimited)
     * @return array Directory tree structure
     */
    public static function tree(string $name, int $maxDepth = -1): array
    {
        $path = self::path($name);

        if ($path === null || !is_dir($path)) {
            return [];
        }

        return self::buildTree($path, 0, $maxDepth);
    }

    /**
     * Build directory tree recursively
     *
     * @param string $path The path to scan
     * @param int $depth Current depth
     * @param int $maxDepth Maximum depth
     * @return array Directory tree
     */
    private static function buildTree(string $path, int $depth, int $maxDepth): array
    {
        if ($maxDepth !== -1 && $depth > $maxDepth) {
            return [];
        }

        $tree = [];
        $items = array_diff(scandir($path), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $path . '/' . $item;
            
            if (is_dir($itemPath)) {
                $tree[$item] = self::buildTree($itemPath, $depth + 1, $maxDepth);
            } else {
                $tree[] = $item;
            }
        }

        return $tree;
    }

    /**
     * Get directory statistics
     *
     * @param string $name The directory identifier
     * @return array|false Statistics array, or false on failure
     */
    public static function stats(string $name)
    {
        $path = self::path($name);

        if ($path === null || !is_dir($path)) {
            return false;
        }

        $fileCount = 0;
        $dirCount = 0;
        $totalSize = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $fileCount++;
                $totalSize += $file->getSize();
            } elseif ($file->isDir()) {
                $dirCount++;
            }
        }

        return [
            'path' => $path,
            'exists' => true,
            'readable' => is_readable($path),
            'writable' => is_writable($path),
            'files' => $fileCount,
            'directories' => $dirCount,
            'total_items' => $fileCount + $dirCount,
            'size_bytes' => $totalSize,
            'size_human' => self::formatBytes($totalSize),
            'permissions' => substr(sprintf('%o', fileperms($path)), -4),
        ];
    }

    /**
     * Format bytes to human-readable format
     *
     * @param int $bytes The bytes to format
     * @param int $precision Decimal precision
     * @return string Formatted size
     */
    private static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Get the count of files in a directory
     *
     * @param string $name The directory identifier
     * @param bool $recursive Count recursively
     * @return int|false The file count, or false on failure
     */
    public static function fileCount(string $name, bool $recursive = false)
    {
        $path = self::path($name);

        if ($path === null || !is_dir($path)) {
            return false;
        }

        $count = 0;

        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }
        } else {
            $items = array_diff(scandir($path), ['.', '..']);
            foreach ($items as $item) {
                if (is_file($path . '/' . $item)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}