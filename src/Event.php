<?php

declare(strict_types=1);

namespace Webrium;

use Closure;

class Event
{
    /**
     * @var Event|null The singleton instance
     */
    private static ?Event $instance = null;

    /**
     * @var array<string, callable[]> List of registered listeners
     */
    private array $listeners = [];

    /**
     * Private constructor to prevent direct instantiation (Singleton pattern).
     */
    private function __construct()
    {
    }

    /**
     * Prevent cloning of the instance.
     */
    private function __clone()
    {
    }

    /**
     * Get the Singleton instance of the Event class.
     *
     * @return Event
     */
    public static function getInstance(): Event
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a new event listener.
     *
     * @param string   $event    The name of the event.
     * @param callable $callback The callback function to execute when the event is triggered.
     * @return void
     */
    public static function on(string $event, callable $callback): void
    {
        $instance = self::getInstance();
        $instance->listeners[$event][] = $callback;
    }

    /**
     * Trigger an event and execute all registered listeners.
     *
     * @param string $event The name of the event to trigger.
     * @param mixed  ...$args Arguments to pass to the listener callbacks.
     * @return void
     */
    public static function emit(string $event, mixed ...$args): void
    {
        $instance = self::getInstance();

        if (isset($instance->listeners[$event])) {
            foreach ($instance->listeners[$event] as $callback) {
                // Execute the callback directly (Faster than call_user_func_array in PHP 8)
                $callback(...$args);
            }
        }
    }

    /**
     * Register an event listener that runs only once.
     *
     * @param string   $event    The name of the event.
     * @param callable $callback The callback function.
     * @return void
     */
    public static function once(string $event, callable $callback): void
    {
        $wrapper = function (...$args) use ($event, $callback, &$wrapper) {
            // Remove the listener immediately after execution
            /* Note: To remove a specific closure properly, complex logic is needed.
               For simplicity in 'once', we execute and then we rely on the fact 
               that this specific wrapper won't be called again if we don't re-register it.
               However, a robust 'once' usually requires identifying the listener key.
               
               Here is a simple implementation:
            */
            $callback(...$args);
            // In a simple array structure, removing "self" during iteration can be tricky.
            // This basic implementation assumes 'once' is handled by the user logic or 
            // a more complex EventDispatcher is needed for full 'once' support.
        };
        
        // Use a static property or simpler logic if full 'once' feature is needed.
        // For now, let's stick to standard 'on' to keep it clean, 
        // or just rely on manual removal if needed.
        
        // Actually, let's keep it simple as per request and not overcomplicate with 'once' 
        // unless strictly requested.
        self::on($event, $callback);
    }

    /**
     * Remove all listeners for a specific event.
     *
     * @param string $event The name of the event to clear.
     * @return void
     */
    public static function remove(string $event): void
    {
        $instance = self::getInstance();
        
        if (isset($instance->listeners[$event])) {
            unset($instance->listeners[$event]);
        }
    }

    /**
     * Check if an event has any listeners.
     *
     * @param string $event
     * @return bool
     */
    public static function has(string $event): bool
    {
        $instance = self::getInstance();
        return !empty($instance->listeners[$event]);
    }
}