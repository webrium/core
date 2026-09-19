<?php

declare(strict_types=1);

namespace Webrium;

/**
 * A single scheduled task: a callback plus the cron expression that decides
 * when it is due, built with a fluent interface.
 */
class ScheduleEvent
{
    /** @var callable|string|array */
    private $callback;

    private string $expression = '* * * * *';
    private ?CronExpression $cron = null;
    private ?string $name = null;

    /**
     * @param callable|string|array $callback Closure, [object|class, method],
     *                                         'Class@method', or a global function name.
     */
    public function __construct($callback)
    {
        $this->callback = $callback;
    }

    /**
     * Assign a stable, human-readable name used for lock files and reports.
     * Recommended for any task, required for closures if a specific report
     * label is wanted (a closure otherwise falls back to its file:line).
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name ?? $this->inferName();
    }

    /**
     * Filesystem-safe identifier derived from the task name, used for the
     * per-task lock file so concurrent/overlapping runs are detected.
     */
    public function lockKey(): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $this->getName()) ?? '';
        return $safe === '' || strlen($safe) > 150 ? sha1($this->getName()) : $safe;
    }

    public function cron(string $expression): self
    {
        $this->expression = $expression;
        $this->cron = null;
        return $this;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): self
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): self
    {
        return $this->cron('0,30 * * * *');
    }

    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $minute): self
    {
        return $this->cron("$minute * * * *");
    }

    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): self
    {
        [$hour, $minute] = self::parseTime($time);
        return $this->cron("$minute $hour * * *");
    }

    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    public function weeklyOn(int $dayOfWeek, string $time = '00:00'): self
    {
        [$hour, $minute] = self::parseTime($time);
        return $this->cron("$minute $hour * * $dayOfWeek");
    }

    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    public function isDue(\DateTimeInterface $at): bool
    {
        return $this->resolvedCron()->isDue($at);
    }

    /**
     * Invoke the task's callback. Throws on failure — callers decide how to
     * isolate/report that (see Schedule::runDue()).
     */
    public function run(): mixed
    {
        return self::invoke($this->callback);
    }

    private function resolvedCron(): CronExpression
    {
        return $this->cron ??= new CronExpression($this->expression);
    }

    /**
     * @return array{0: int, 1: int} [hour, minute]
     */
    private static function parseTime(string $time): array
    {
        $parts = array_pad(explode(':', $time), 2, '0');
        return [(int) $parts[0], (int) $parts[1]];
    }

    /**
     * @param  callable|string|array $callback
     */
    private static function invoke($callback): mixed
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            [$class, $method] = explode('@', $callback, 2);

            if (!class_exists($class)) {
                throw new \RuntimeException("Scheduled task class '$class' not found.");
            }

            $callback = [new $class(), $method];
        }

        if (!is_callable($callback)) {
            throw new \RuntimeException('Scheduled task callback is not callable.');
        }

        return call_user_func($callback);
    }

    private function inferName(): string
    {
        if (is_string($this->callback)) {
            return $this->callback;
        }

        if (is_array($this->callback)) {
            [$target, $method] = $this->callback;
            $class = is_object($target) ? get_class($target) : $target;
            return "$class::$method";
        }

        if ($this->callback instanceof \Closure) {
            $ref = new \ReflectionFunction($this->callback);
            return 'closure@' . $ref->getFileName() . ':' . $ref->getStartLine();
        }

        return 'task@' . spl_object_id($this);
    }
}
