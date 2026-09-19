<?php

declare(strict_types=1);

namespace Webrium;

/**
 * File-based advisory lock (flock) preventing a scheduled task from being
 * run concurrently with itself — e.g. a 5-minute task still running when
 * the next scheduler tick fires.
 *
 * Single-server only: two application servers sharing no filesystem will
 * each acquire their own lock independently. Distributed locking across
 * servers is out of scope for this class.
 */
class ScheduleLock
{
    /** @var resource|null */
    private $handle = null;
    private string $path;

    public function __construct(string $key)
    {
        $dir = Directory::path('schedule_locks');

        if ($dir === null) {
            throw new \RuntimeException(
                "The 'schedule_locks' directory is not registered. Call Directory::initDefaultStructure() first."
            );
        }

        $this->path = rtrim($dir, '/\\') . '/' . $key . '.lock';
    }

    /**
     * Try to acquire the lock without blocking.
     *
     * @return bool True if acquired, false if another process already holds it.
     */
    public function acquire(): bool
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $handle = @fopen($this->path, 'c');
        if ($handle === false) {
            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        $this->handle = $handle;
        return true;
    }

    public function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }
}
