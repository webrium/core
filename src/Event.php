<?php

declare(strict_types=1);

namespace Webrium;

class Event
{
    private static ?Event $instance = null;

    /**
     * Persistent listeners — run every time the event is emitted.
     * Structure: [ eventName => [ callable, ... ] ]
     *
     * @var array<string, callable[]>
     */
    private array $listeners = [];

    /**
     * One-shot listeners — removed immediately after their first execution.
     * Each entry stores the original callback so it can be matched for removal.
     * Structure: [ eventName => [ ['wrapper' => callable, 'original' => callable], ... ] ]
     *
     * @var array<string, array<int, array{wrapper: callable, original: callable}>>
     */
    private array $onceListeners = [];

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): Event
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a persistent event listener.
     *
     * @param string   $event    Event name.
     * @param callable $callback Invoked every time the event is emitted.
     * @return void
     */
    public static function on(string $event, callable $callback): void
    {
        self::getInstance()->listeners[$event][] = $callback;
    }

    /**
     * Register a one-shot event listener.
     *
     * The callback is invoked the first time the event fires and then
     * automatically removed — it will never run again.
     *
     * @param string   $event    Event name.
     * @param callable $callback Invoked once on first emission.
     * @return void
     */
    public static function once(string $event, callable $callback): void
    {
        $instance = self::getInstance();

        $wrapper = function () use ($event, $callback, &$wrapper, $instance): void {
            // Remove this one-shot entry before invoking the callback so that
            // even if the callback re-emits the same event it won't fire again.
            $instance->removeOnceWrapper($event, $wrapper);

            $callback(...func_get_args());
        };

        $instance->onceListeners[$event][] = [
            'wrapper'  => $wrapper,
            'original' => $callback,
        ];
    }

    /**
     * Emit an event, invoking all matching persistent and one-shot listeners.
     *
     * One-shot listeners are dequeued before being called so that re-entrant
     * emissions of the same event inside a once-callback do not trigger them
     * a second time.
     *
     * @param string $event  Event name.
     * @param mixed  ...$args Arguments forwarded to every listener.
     * @return void
     */
    public static function emit(string $event, mixed ...$args): void
    {
        $instance = self::getInstance();

        // Drain one-shot listeners first: copy the current list, clear it,
        // then call each wrapper (which internally removes itself again — harmless).
        if (!empty($instance->onceListeners[$event])) {
            $toRun = $instance->onceListeners[$event];
            unset($instance->onceListeners[$event]);

            foreach ($toRun as $entry) {
                ($entry['wrapper'])(...$args);
            }
        }

        // Persistent listeners
        if (!empty($instance->listeners[$event])) {
            foreach ($instance->listeners[$event] as $callback) {
                $callback(...$args);
            }
        }
    }

    /**
     * Remove all persistent and one-shot listeners for the given event.
     *
     * @param string $event Event name.
     * @return void
     */
    public static function remove(string $event): void
    {
        $instance = self::getInstance();
        unset($instance->listeners[$event], $instance->onceListeners[$event]);
    }

    /**
     * Check whether any listener (persistent or one-shot) is registered for an event.
     *
     * @param string $event Event name.
     * @return bool
     */
    public static function has(string $event): bool
    {
        $instance = self::getInstance();
        return !empty($instance->listeners[$event])
            || !empty($instance->onceListeners[$event]);
    }

    /**
     * Remove a specific one-shot wrapper from the pending list.
     * Called internally by the wrapper closure before it executes.
     *
     * @param string   $event   Event name.
     * @param callable $wrapper The wrapper closure to remove.
     * @return void
     */
    private function removeOnceWrapper(string $event, callable $wrapper): void
    {
        if (empty($this->onceListeners[$event])) {
            return;
        }

        foreach ($this->onceListeners[$event] as $index => $entry) {
            if ($entry['wrapper'] === $wrapper) {
                array_splice($this->onceListeners[$event], $index, 1);
                break;
            }
        }
    }
}
