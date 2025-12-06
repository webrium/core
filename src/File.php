<?php

namespace Webrium;

use Webrium\App;
use Webrium\Debug;
use Webrium\Directory;

/**
 * File Manager Class
 * 
 * A comprehensive file management utility that provides methods for file operations,
 * streaming, downloading, and directory management.
 * 
 * Features:
 * - File existence and validation checks
 * - File reading and writing operations
 * - File streaming with range support (for video/audio)
 * - File download functionality
 * - Image serving with proper MIME types
 * - Directory operations (recursive delete, file listing)
 * - File hashing and metadata retrieval
 * - PHP file execution (include/require)
 * - Controller method execution
 * 
 * @package Webrium
 * @version 2.0.0
 */
class File
{
    /**
     * Default buffer size for streaming operations (8KB)
     *
     * @var int
     */
    private const STREAM_BUFFER_SIZE = 8192;

    /**
     * Common image MIME types
     *
     * @var array
     */
    private const IMAGE_MIME_TYPES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'webp' => 'image/webp',
        'bmp'  => 'image/bmp',
        'ico'  => 'image/x-icon',
    ];

    /**
     * Check if a file or directory exists at the given path
     *
     * @param string $path The path to check
     * @return bool True if the file exists, false otherwise
     */
    public static function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Check if the path is a file (not a directory)
     *
     * @param string $path The path to check
     * @return bool True if the path is a file, false otherwise
     */
    public static function isFile(string $path): bool
    {
        return is_file($path);
    }

    /**
     * Check if the path is a directory
     *
     * @param string $path The path to check
     * @return bool True if the path is a directory, false otherwise
     */
    public static function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * Check if a file is readable
     *
     * @param string $path The path to the file
     * @return bool True if the file is readable, false otherwise
     */
    public static function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    /**
     * Check if a file is writable
     *
     * @param string $path The path to the file
     * @return bool True if the file is writable, false otherwise
     */
    public static function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    /**
     * Get the size of a file in bytes
     *
     * @param string $path The path to the file
     * @return int|false The file size in bytes, or false on failure
     */
    public static function size(string $path)
    {
        return self::exists($path) ? filesize($path) : false;
    }

    /**
     * Get the last modified time of a file
     *
     * @param string $path The path to the file
     * @return int|false Unix timestamp of last modification, or false on failure
     */
    public static function lastModified(string $path)
    {
        return self::exists($path) ? filemtime($path) : false;
    }

    /**
     * Get the file extension
     *
     * @param string $path The path to the file
     * @return string The file extension (lowercase, without dot)
     */
    public static function extension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    /**
     * Get the filename without extension
     *
     * @param string $path The path to the file
     * @return string The filename without extension
     */
    public static function name(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Get the basename of a file (filename with extension)
     *
     * @param string $path The path to the file
     * @return string The basename
     */
    public static function basename(string $path): string
    {
        return basename($path);
    }

    /**
     * Get the directory name of a file path
     *
     * @param string $path The path to the file
     * @return string The directory path
     */
    public static function dirname(string $path): string
    {
        return dirname($path);
    }

    /**
     * Get the MIME type of a file
     *
     * @param string $path The path to the file
     * @return string|false The MIME type, or false on failure
     */
    public static function mimeType(string $path)
    {
        if (!self::exists($path)) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mimeType;
    }

    /**
     * Include and execute a PHP file
     *
     * @param string $path The path to the file
     * @return bool True if the file was successfully included, false otherwise
     */
    public static function run(string $path): bool
    {
        if (self::exists($path)) {
            include $path;
            return true;
        }
        return false;
    }

    /**
     * Include and execute a PHP file only once
     *
     * @param string $path The path to the file
     * @return bool True if the file was successfully included, false otherwise
     */
    public static function runOnce(string $path): bool
    {
        if (self::exists($path)) {
            include_once $path;
            return true;
        }
        return false;
    }

    /**
     * Require a PHP file (throws error if not found)
     *
     * @param string $path The path to the file
     * @return bool True if the file was successfully required
     */
    public static function requireFile(string $path): bool
    {
        if (self::exists($path)) {
            require $path;
            return true;
        }
        return false;
    }

    /**
     * Require a PHP file only once
     *
     * @param string $path The path to the file
     * @return bool True if the file was successfully required
     */
    public static function requireOnce(string $path): bool
    {
        if (self::exists($path)) {
            require_once $path;
            return true;
        }
        return false;
    }

    /**
     * Include multiple files from a directory based on an array of filenames
     *
     * @param string $pathName The name or path of the directory
     * @param array $files An array of filenames to include
     * @return int Number of files successfully included
     */
    public static function source(string $pathName, array $files): int
    {
        $path = Directory::path($pathName);
        $included = 0;

        foreach ($files as $file) {
            if (self::runOnce("$path/$file")) {
                $included++;
            }
        }

        return $included;
    }

    /**
     * Execute a method on a controller class
     *
     * @param string $dirName The name of the directory
     * @param string $className The name of the class
     * @param string $methodName The name of the method to execute
     * @param array $params An array of parameters to pass to the method
     * @return void
     */
    public static function executeControllerMethod(
        string $dirName,
        string $className,
        string $methodName,
        array $params = []
    ): void {
        $dir = Directory::get($dirName);
        $class = "$dir\\$className";
        $class = str_replace('/', '\\', $class);

        if (!class_exists($class)) {
            Debug::triggerError("Class $class not found", "$className.php");
            return;
        }

        $controller = new $class();

        // Call initialization method if exists
        if (method_exists($controller, '__init')) {
            $controller->__init();
        }

        // Execute the main method
        if (method_exists($controller, $methodName)) {
            App::ReturnData($controller->{$methodName}(...$params));
        } else {
            Debug::triggerError("Method $methodName not found in $class", "$className.php");
        }

        // Call cleanup method if exists
        if (method_exists($controller, '__end')) {
            $controller->__end();
        }
    }

    /**
     * Read the contents of a file
     *
     * @param string $path The path to the file
     * @return string|false The file contents, or false on failure
     */
    public static function read(string $path)
    {
        return self::exists($path) ? file_get_contents($path) : false;
    }

    /**
     * Get the content of a file (alias for read)
     *
     * @param string $path The path to the file
     * @return string|false The file contents, or false on failure
     */
    public static function getContent(string $path)
    {
        return self::read($path);
    }

    /**
     * Write content to a file
     *
     * @param string $path The path to the file
     * @param string $content The content to write
     * @param bool $append Whether to append to the file (default: false)
     * @return int|false The number of bytes written, or false on failure
     */
    public static function write(string $path, string $content, bool $append = false)
    {
        $flags = $append ? FILE_APPEND : 0;
        return file_put_contents($path, $content, $flags);
    }

    /**
     * Write content to a file (alias for write)
     *
     * @param string $path The path to the file
     * @param string $content The content to write
     * @return int|false The number of bytes written, or false on failure
     */
    public static function putContent(string $path, string $content)
    {
        return self::write($path, $content);
    }

    /**
     * Append content to a file
     *
     * @param string $path The path to the file
     * @param string $content The content to append
     * @return int|false The number of bytes written, or false on failure
     */
    public static function append(string $path, string $content)
    {
        return self::write($path, $content, true);
    }

    /**
     * Prepend content to a file
     *
     * @param string $path The path to the file
     * @param string $content The content to prepend
     * @return int|false The number of bytes written, or false on failure
     */
    public static function prepend(string $path, string $content)
    {
        $existing = self::read($path);
        return self::write($path, $content . $existing);
    }

    /**
     * Read a file line by line
     *
     * @param string $path The path to the file
     * @return array|false Array of lines, or false on failure
     */
    public static function lines(string $path)
    {
        if (!self::exists($path)) {
            return false;
        }
        return file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    /**
     * Copy a file to a new location
     *
     * @param string $source The source file path
     * @param string $destination The destination file path
     * @return bool True on success, false on failure
     */
    public static function copy(string $source, string $destination): bool
    {
        if (!self::exists($source)) {
            return false;
        }
        return copy($source, $destination);
    }

    /**
     * Move/rename a file
     *
     * @param string $source The source file path
     * @param string $destination The destination file path
     * @return bool True on success, false on failure
     */
    public static function move(string $source, string $destination): bool
    {
        if (!self::exists($source)) {
            return false;
        }
        return rename($source, $destination);
    }

    /**
     * Delete a file
     *
     * @param string $path The path to the file
     * @return bool True if the file was successfully deleted, false otherwise
     */
    public static function delete(string $path): bool
    {
        return self::exists($path) ? unlink($path) : false;
    }

    /**
     * Delete multiple files
     *
     * @param array $paths Array of file paths to delete
     * @return int Number of files successfully deleted
     */
    public static function deleteMultiple(array $paths): int
    {
        $deleted = 0;
        foreach ($paths as $path) {
            if (self::delete($path)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Recursively delete a directory and its contents
     *
     * @param string $dir The path to the directory
     * @return bool True if the directory was successfully deleted, false otherwise
     */
    public static function deleteDirectory(string $dir): bool
    {
        if (!self::isDirectory($dir)) {
            return false;
        }

        $files = self::getFiles($dir, []);

        foreach ($files as $file) {
            $path = "$dir/$file";
            if (self::isDirectory($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * Alias for deleteDirectory
     *
     * @param string $dir The path to the directory
     * @return bool True if the directory was successfully deleted, false otherwise
     */
    public static function delete_dir(string $dir): bool
    {
        return self::deleteDirectory($dir);
    }

    /**
     * Create a directory
     *
     * @param string $path The path to the directory
     * @param int $mode The permissions (default: 0755)
     * @param bool $recursive Create parent directories if needed (default: true)
     * @return bool True on success, false on failure
     */
    public static function makeDirectory(string $path, int $mode = 0755, bool $recursive = true): bool
    {
        if (self::isDirectory($path)) {
            return true;
        }
        return mkdir($path, $mode, $recursive);
    }

    /**
     * Get all files in a directory
     *
     * @param string $path The path to the directory
     * @param array $exclude An array of filenames to exclude
     * @return array Array of filenames
     */
    public static function getFiles(string $path, array $exclude = ['.', '..', '.gitignore']): array
    {
        if (!self::isDirectory($path)) {
            return [];
        }

        $files = [];
        $scanned = array_diff(scandir($path), $exclude);

        foreach ($scanned as $file) {
            $files[] = $file;
        }

        return $files;
    }

    /**
     * Get all files in a directory recursively
     *
     * @param string $path The path to the directory
     * @param array $exclude An array of filenames to exclude
     * @return array Array of file paths
     */
    public static function getFilesRecursive(string $path, array $exclude = ['.', '..', '.gitignore']): array
    {
        $files = [];
        $items = self::getFiles($path, $exclude);

        foreach ($items as $item) {
            $fullPath = "$path/$item";
            if (self::isDirectory($fullPath)) {
                $files = array_merge($files, self::getFilesRecursive($fullPath, $exclude));
            } else {
                $files[] = $fullPath;
            }
        }

        return $files;
    }

    /**
     * Stream a file with support for range requests (useful for video/audio)
     *
     * @param string $filePath The path to the file
     * @param string|null $downloadName Optional download filename
     * @return void
     */
    public static function stream(string $filePath, ?string $downloadName = null): void
    {
        if (!self::isFile($filePath)) {
            header('HTTP/1.1 404 Not Found');
            echo "Error: File not found.";
            exit;
        }

        $fileSize = filesize($filePath);
        $downloadName = $downloadName ?? basename($filePath);
        $mimeType = self::mimeType($filePath);

        // Set default headers
        header('Content-Type: ' . $mimeType);
        header('Accept-Ranges: bytes');

        // Handle range requests
        if (isset($_SERVER['HTTP_RANGE'])) {
            self::streamPartial($filePath, $fileSize);
        } else {
            self::streamFull($filePath, $fileSize);
        }

        exit;
    }

    /**
     * Stream a partial file (range request)
     *
     * @param string $filePath The path to the file
     * @param int $fileSize The file size
     * @return void
     */
    private static function streamPartial(string $filePath, int $fileSize): void
    {
        $range = $_SERVER['HTTP_RANGE'];
        list(, $range) = explode('=', $range, 2);
        list($start, $end) = explode('-', $range);

        $start = $start === '' ? 0 : intval($start);
        $end = $end === '' ? $fileSize - 1 : intval($end);

        // Validate range
        if ($start > $end || $start >= $fileSize) {
            header('HTTP/1.1 416 Range Not Satisfiable');
            header("Content-Range: bytes */$fileSize");
            return;
        }

        $length = $end - $start + 1;

        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes $start-$end/$fileSize");
        header('Content-Length: ' . $length);

        $file = fopen($filePath, 'rb');
        fseek($file, $start);
        
        $bufferSize = self::STREAM_BUFFER_SIZE;
        while (!feof($file) && ($pos = ftell($file)) <= $end) {
            if ($pos + $bufferSize > $end) {
                $bufferSize = $end - $pos + 1;
            }
            echo fread($file, $bufferSize);
            ob_flush();
            flush();
        }
        
        fclose($file);
    }

    /**
     * Stream the full file
     *
     * @param string $filePath The path to the file
     * @param int $fileSize The file size
     * @return void
     */
    private static function streamFull(string $filePath, int $fileSize): void
    {
        header('Content-Length: ' . $fileSize);
        readfile($filePath);
    }

    /**
     * Download a file with proper headers
     *
     * @param string $filePath The path to the file
     * @param string|null $downloadName The filename for download
     * @return void
     */
    public static function download(string $filePath, ?string $downloadName = null): void
    {
        if (!self::isFile($filePath)) {
            header('HTTP/1.1 404 Not Found');
            echo "Error: File not found.";
            exit;
        }

        $fileSize = filesize($filePath);
        $downloadName = $downloadName ?? basename($filePath);
        $mimeType = self::mimeType($filePath);

        // Set headers for download
        header('Content-Type: ' . $mimeType);
        header('Content-Transfer-Encoding: Binary');
        header('Content-Length: ' . $fileSize);
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');

        // Stream the file
        $file = fopen($filePath, 'rb');
        while (!feof($file)) {
            echo fread($file, self::STREAM_BUFFER_SIZE);
            ob_flush();
            flush();
        }
        fclose($file);
        exit;
    }

    /**
     * Serve an image with proper MIME type
     *
     * @param string $path The path to the image
     * @return void
     */
    public static function showImage(string $path): void
    {
        if (!self::exists($path)) {
            http_response_code(404);
            echo '404 file not found';
            exit;
        }

        $extension = self::extension($path);
        $mimeType = self::IMAGE_MIME_TYPES[$extension] ?? self::mimeType($path);

        header('Content-Type: ' . $mimeType);
        echo file_get_contents($path);
        exit;
    }

    /**
     * Get the MD5 hash of a file
     *
     * @param string $path The path to the file
     * @return string|false The MD5 hash, or false on failure
     */
    public static function hash(string $path)
    {
        return self::exists($path) ? md5_file($path) : false;
    }

    /**
     * Get the SHA-1 hash of a file
     *
     * @param string $path The path to the file
     * @return string|false The SHA-1 hash, or false on failure
     */
    public static function sha1(string $path)
    {
        return self::exists($path) ? sha1_file($path) : false;
    }

    /**
     * Get a hash of a file using specified algorithm
     *
     * @param string $path The path to the file
     * @param string $algorithm The hash algorithm (md5, sha1, sha256, etc.)
     * @return string|false The hash, or false on failure
     */
    public static function hashFile(string $path, string $algorithm = 'md5')
    {
        return self::exists($path) ? hash_file($algorithm, $path) : false;
    }

    /**
     * Get file permissions
     *
     * @param string $path The path to the file
     * @return int|false The file permissions, or false on failure
     */
    public static function permissions(string $path)
    {
        return self::exists($path) ? fileperms($path) : false;
    }

    /**
     * Set file permissions
     *
     * @param string $path The path to the file
     * @param int $mode The permissions mode (e.g., 0644)
     * @return bool True on success, false on failure
     */
    public static function chmod(string $path, int $mode): bool
    {
        return self::exists($path) ? chmod($path, $mode) : false;
    }

    /**
     * Get the file owner
     *
     * @param string $path The path to the file
     * @return int|false The user ID of the owner, or false on failure
     */
    public static function owner(string $path)
    {
        return self::exists($path) ? fileowner($path) : false;
    }

    /**
     * Get human-readable file size
     *
     * @param string $path The path to the file
     * @param int $precision Decimal precision (default: 2)
     * @return string|false Human-readable size, or false on failure
     */
    public static function humanSize(string $path, int $precision = 2)
    {
        $bytes = self::size($path);
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
     * Check if a file matches a glob pattern
     *
     * @param string $path The path to the file
     * @param string $pattern The glob pattern
     * @return bool True if the file matches the pattern
     */
    public static function matches(string $path, string $pattern): bool
    {
        return fnmatch($pattern, $path);
    }

    /**
     * Get files matching a glob pattern
     *
     * @param string $pattern The glob pattern
     * @param int $flags Optional flags for glob()
     * @return array|false Array of matching files, or false on failure
     */
    public static function glob(string $pattern, int $flags = 0)
    {
        return glob($pattern, $flags);
    }
}