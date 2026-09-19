<?php

declare(strict_types=1);

namespace Webrium;

/**
 * Task scheduling registry.
 *
 * Tasks are registered with Schedule::call() — typically from files under
 * the 'schedules' directory (app/Schedules/ by default), each loaded in
 * isolation by loadFromDirectory() so one broken file cannot stop the rest
 * from registering. runDue() then executes whichever registered tasks are
 * due, isolating each task's own failure the same way.
 *
 * Intended to be driven by a single system cron entry running the
 * `schedule:run` console command once a minute (see webrium/console),
 * mirroring Laravel's scheduler model.
 */
class Schedule
{
    /** @var ScheduleEvent[] */
    private static array $events = [];

    /**
     * @param  callable|string|array $callback
     */
    public static function call($callback): ScheduleEvent
    {
        $event = new ScheduleEvent($callback);
        self::$events[] = $event;
        return $event;
    }

    /** @return ScheduleEvent[] */
    public static function all(): array
    {
        return self::$events;
    }

    /**
     * Find a registered task by its name (see ScheduleEvent::getName()).
     */
    public static function find(string $name): ?ScheduleEvent
    {
        foreach (self::$events as $event) {
            if ($event->getName() === $name) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Clear the registry. Mainly useful for tests and for reloading the
     * schedule directory from a clean state.
     */
    public static function reset(): void
    {
        self::$events = [];
    }

    /**
     * Require every *.php file under $directory (recursively — so plugins
     * can namespace their own task file under a subdirectory without
     * colliding with anyone else's), isolating each file's own load error
     * so a broken file never prevents the rest of the schedule from
     * registering.
     *
     * @return array<int, array{file: string, error: string}> Load failures, if any.
     */
    public static function loadFromDirectory(string $directory): array
    {
        $errors = [];

        if (!is_dir($directory)) {
            return $errors;
        }

        $files = array_values(array_filter(
            File::getFilesRecursive($directory),
            static fn (string $file): bool => str_ends_with(strtolower($file), '.php')
        ));
        sort($files);

        foreach ($files as $file) {
            try {
                require $file;
            } catch (\Throwable $e) {
                $errors[] = ['file' => $file, 'error' => $e->getMessage()];
            }
        }

        return $errors;
    }

    /**
     * Convenience wrapper loading from the registered 'schedules' directory
     * (app/Schedules/ by default).
     *
     * @return array<int, array{file: string, error: string}>
     */
    public static function loadDefault(): array
    {
        $dir = Directory::path('schedules');
        return $dir === null ? [] : self::loadFromDirectory($dir);
    }

    /**
     * Run every registered task that is due at $now (defaults to the
     * current time). Each task is isolated: a failing or already-running
     * task is reported but never stops the rest from being attempted.
     *
     * @return array<int, array{name: string, status: 'ran'|'skipped'|'failed', error: string|null}>
     */
    public static function runDue(?\DateTimeInterface $now = null): array
    {
        $now = $now ?? new \DateTimeImmutable();
        $report = [];

        foreach (self::$events as $event) {
            if ($event->isDue($now)) {
                $report[] = self::run($event);
            }
        }

        return $report;
    }

    /**
     * Run a single task immediately, bypassing its own due-check — used by
     * runDue() for each due task, and directly by tooling like
     * webrium/console's `schedule:test` to trigger one task on demand.
     * Isolated the same way as runDue(): a failure is reported, never
     * thrown, and the task's own overlap lock still applies.
     *
     * @return array{name: string, status: 'ran'|'skipped'|'failed', error: string|null}
     */
    public static function run(ScheduleEvent $event): array
    {
        $name = $event->getName();
        $lock = new ScheduleLock($event->lockKey());

        if (!$lock->acquire()) {
            return ['name' => $name, 'status' => 'skipped', 'error' => 'already running'];
        }

        try {
            $event->run();
            return ['name' => $name, 'status' => 'ran', 'error' => null];
        } catch (\Throwable $e) {
            Debug::triggerError(
                "Scheduled task '$name' failed: " . $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                500,
                false,
                'ScheduleTaskError'
            );

            return ['name' => $name, 'status' => 'failed', 'error' => $e->getMessage()];
        } finally {
            $lock->release();
        }
    }
}
