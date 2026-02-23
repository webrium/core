<?php

declare(strict_types=1);

namespace Webrium;

use Exception;
use finfo;
use Throwable;

class Upload
{
    protected string $name;
    protected string $tmpName;
    protected string $type;
    protected int $size;
    protected int $error;

    protected ?string $destinationPath = null;
    protected ?string $targetFileName = null;
    protected array $validationErrors = [];

    protected ?int $maxSize = null;
    protected array $allowedExtensions = [];
    protected array $allowedMimeTypes = [];

    protected bool $preventOverwrite = true;
    protected int $maxNameLength = 255;

    protected function __construct(array $fileData)
    {
        $this->name    = (string) ($fileData['name'] ?? '');
        $this->tmpName = (string) ($fileData['tmp_name'] ?? '');
        $this->type    = (string) ($fileData['type'] ?? '');
        $this->size    = (int) ($fileData['size'] ?? 0);
        $this->error   = (int) ($fileData['error'] ?? UPLOAD_ERR_NO_FILE);
    }

    /**
     * Create Upload instance(s) from input name.
     *
     * @param string $inputName The name of the file input field
     * @return Upload|array|null Returns Upload object, array of Upload objects, or null if empty
     */
    public static function fromInput(string $inputName): Upload|array|null
    {
        if (!isset($_FILES[$inputName])) {
            return null;
        }

        $file = $_FILES[$inputName];

        // Handle Array Input (e.g., <input name="files[]" ...>)
        if (is_array($file['name'])) {
            $collection = [];
            $count = count($file['name']);

            for ($i = 0; $i < $count; $i++) {
                if (empty($file['name'][$i])) {
                    continue;
                }

                $collection[] = new static([
                    'name'     => $file['name'][$i],
                    'type'     => $file['type'][$i] ?? '',
                    'tmp_name' => $file['tmp_name'][$i] ?? '',
                    'error'    => $file['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $file['size'][$i] ?? 0,
                ]);
            }
            return empty($collection) ? null : $collection;
        }

        // Handle Single Input
        if (empty($file['name'])) {
            return null;
        }

        return new static($file);
    }

    // --- Configuration Methods ---

    public function maxKB(int $kb): self
    {
        $this->maxSize = $kb * 1024;
        return $this;
    }

    public function maxMB(int $mb): self
    {
        $this->maxSize = $mb * 1024 * 1024;
        return $this;
    }

    public function allowExtension(array|string $extensions): self
    {
        if (is_string($extensions)) {
            $extensions = explode(',', $extensions);
        }

        $this->allowedExtensions = array_map(function ($ext) {
            return ltrim(strtolower(trim($ext)), '.');
        }, $extensions);

        return $this;
    }

    public function allowMimeType(array|string $types): self
    {
        if (is_string($types)) {
            $types = explode(',', $types);
        }
        $this->allowedMimeTypes = array_map('trim', $types);
        return $this;
    }

    public function to(string $path): self
    {
        $this->destinationPath = rtrim($path, DIRECTORY_SEPARATOR);
        return $this;
    }

    /**
     * Set a custom name for the saved file.
     *
     * The original file's extension is always preserved regardless of
     * what extension (if any) is included in $name, preventing extension
     * spoofing via the custom name.
     *
     * @param string $name Desired base name (with or without extension)
     */
    public function asName(string $name): self
    {
        $realExt = $this->getExtension();

        // Strip any extension the caller may have included in $name
        $baseName = pathinfo($name, PATHINFO_FILENAME);

        // Sanitize the base name
        $baseName = $this->sanitizeFileName($baseName);

        // Always use the real extension from the uploaded file
        $this->targetFileName = $baseName . ($realExt !== '' ? '.' . $realExt : '');

        return $this;
    }

    public function useRandomName(): self
    {
        $ext = $this->getExtension();
        $this->targetFileName = bin2hex(random_bytes(16));

        if ($ext !== '') {
            $this->targetFileName .= '.' . $ext;
        }
        return $this;
    }

    public function allowOverwrite(bool $allow = true): self
    {
        $this->preventOverwrite = !$allow;
        return $this;
    }

    // --- Action Methods ---

    public function validate(): bool
    {
        $this->validationErrors = [];

        if ($this->error !== UPLOAD_ERR_OK) {
            $this->validationErrors[] = $this->getUploadErrorMessage($this->error);
            return false;
        }

        if (!is_uploaded_file($this->tmpName)) {
            $this->validationErrors[] = 'Temporary file is not a valid uploaded file.';
            return false;
        }

        if ($this->maxSize !== null && $this->size > $this->maxSize) {
            $this->validationErrors[] = "File size ({$this->getSizeFormatted()}) exceeds the limit.";
        }

        if (!empty($this->allowedExtensions)) {
            $ext = $this->getExtension();
            if ($ext === '' || !in_array($ext, $this->allowedExtensions, true)) {
                $this->validationErrors[] = "Extension '.{$ext}' is not allowed.";
            }
        }

        if (!empty($this->allowedMimeTypes)) {
            $realMime = $this->getMimeType();
            if ($realMime === '') {
                $this->validationErrors[] = 'Unable to determine file mime type.';
            } elseif (!in_array($realMime, $this->allowedMimeTypes, true)) {
                $this->validationErrors[] = "File type '{$realMime}' is not allowed.";
            }
        }

        return empty($this->validationErrors);
    }

    public function save(bool $throwOnError = false): bool|string
    {
        if (!$this->destinationPath) {
            $error = "Destination path not set. Use ->to('/path')";
            if ($throwOnError) throw new Exception($error);
            $this->validationErrors[] = $error;
            return false;
        }

        if (!$this->validate()) {
            if ($throwOnError) throw new Exception(implode('; ', $this->validationErrors));
            return false;
        }

        // Ensure directory exists
        if (!is_dir($this->destinationPath)) {
            if (!mkdir($this->destinationPath, 0755, true)) {
                $lastError = error_get_last();
                $reason    = $lastError['message'] ?? 'unknown reason';
                $error     = "Failed to create directory '{$this->destinationPath}': {$reason}";
                if ($throwOnError) throw new Exception($error);
                $this->validationErrors[] = $error;
                return false;
            }
        }

        if (!is_writable($this->destinationPath)) {
            $error = 'Destination path is not writable.';
            if ($throwOnError) throw new Exception($error);
            $this->validationErrors[] = $error;
            return false;
        }

        // Prepare Filename
        $finalName = $this->targetFileName ?? $this->sanitizeFileName($this->name);
        $finalName = $this->ensureNameLength($finalName);
        $fullPath  = $this->destinationPath . DIRECTORY_SEPARATOR . $finalName;

        // Handle Overwrite / Unique naming
        if ($this->preventOverwrite && file_exists($fullPath)) {
            $finalName = $this->generateUniqueName($finalName);
            $fullPath  = $this->destinationPath . DIRECTORY_SEPARATOR . $finalName;
        }

        // Move File
        if (!move_uploaded_file($this->tmpName, $fullPath)) {
            $lastError = error_get_last();
            $reason    = $lastError['message'] ?? 'unknown reason';
            $error     = "Failed to move uploaded file: {$reason}";
            if ($throwOnError) throw new Exception($error);
            $this->validationErrors[] = $error;
            return false;
        }

        // Set Permissions
        @chmod($fullPath, 0644);

        return $finalName;
    }

    // --- Getter Methods ---

    public function getExtension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    public function getOriginalName(): string
    {
        return $this->name;
    }

    /**
     * Get raw file size in bytes.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get the real MIME type by inspecting file contents (not the browser-reported type).
     * Creates a single shared finfo instance per request lifecycle.
     */
    public function getMimeType(): string
    {
        static $finfo = null;

        if ($finfo === null) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
        }

        try {
            $mime = $finfo->file($this->tmpName);
            return $mime ?: '';
        } catch (Throwable $e) {
            return '';
        }
    }

    public function getErrors(): array
    {
        return $this->validationErrors;
    }

    public function getFirstError(): string
    {
        return $this->validationErrors[0] ?? '';
    }

    // --- Protected Helpers ---

    protected function getSizeFormatted(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size  = max(0, $this->size);
        $power = $size > 0 ? (int) floor(log($size, 1024)) : 0;
        $power = min($power, count($units) - 1);
        return number_format($size / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Sanitize a file name by stripping dangerous characters and path components.
     *
     * Rules applied:
     *  - basename() strips directory traversal attempts
     *  - Only alphanumeric, dot, underscore, and hyphen are kept
     *  - Multiple dots are collapsed so double extensions cannot be used
     *  - Leading dots are removed to prevent hidden-file creation
     *  - Falls back to a random hex name if nothing remains after sanitization
     *
     * @param string $name Raw file name
     * @return string      Safe file name
     */
    protected function sanitizeFileName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9.\-_]/', '', $name);

        // Collapse multiple dots to prevent double-extension attacks
        if (substr_count($name, '.') > 1) {
            $parts = explode('.', $name);
            $ext   = array_pop($parts);
            $name  = implode('-', $parts) . '.' . $ext;
        }

        $name = ltrim($name, '.');
        return $name ?: bin2hex(random_bytes(8));
    }

    protected function ensureNameLength(string $name): string
    {
        if (strlen($name) <= $this->maxNameLength) {
            return $name;
        }

        $ext     = pathinfo($name, PATHINFO_EXTENSION);
        $base    = pathinfo($name, PATHINFO_FILENAME);
        $allowed = $this->maxNameLength - ($ext !== '' ? strlen($ext) + 1 : 0);
        $base    = substr($base, 0, max(1, $allowed));

        return $base . ($ext ? '.' . $ext : '');
    }

    protected function generateUniqueName(string $name): string
    {
        $ext     = pathinfo($name, PATHINFO_EXTENSION);
        $base    = pathinfo($name, PATHINFO_FILENAME);
        $counter = 0;

        do {
            $counter++;
            $candidate = $base . '-' . $counter . ($ext ? '.' . $ext : '');
        } while (file_exists($this->destinationPath . DIRECTORY_SEPARATOR . $candidate));

        return $candidate;
    }

    protected function getUploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension',
            default               => 'Unknown upload error',
        };
    }
}