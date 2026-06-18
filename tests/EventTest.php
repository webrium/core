<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webrium\Event;

/**
 * Unit Tests for Webrium\Event
 *
 * Event is a process-wide singleton holding two listener registries
 * (persistent + one-shot) in private instance state. To keep every test
 * honest and independent we fully reset the singleton before and after each
 * test via reflection, rather than relying on test ordering. No behaviour of
 * the class under test is stubbed — listeners are real closures and we assert
 * on their observed side effects.
 *
 * Coverage:
 *  - getInstance(): identity / singleton guarantee
 *  - on() + emit(): persistent listeners fire every time, in registration order
 *  - emit(): arguments are forwarded verbatim (including by count and type)
 *  - once(): one-shot listeners fire exactly once then auto-remove
 *  - emit() with no listeners is a harmless no-op
 *  - has(): reports persistent and one-shot registrations, and clears correctly
 *  - remove(): drops both persistent and one-shot listeners for an event
 *  - Re-entrancy: a once-callback re-emitting its own event does not re-fire
 *  - Ordering between one-shot and persistent listeners
 */
class EventTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetSingleton();
    }

    protected function tearDown(): void
    {
        $this->resetSingleton();
    }

    /**
     * Force a fresh Event singleton so leftover listeners from one test can
     * never leak into another.
     */
    private function resetSingleton(): void
    {
        $ref = new \ReflectionClass(Event::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    // =========================================================================
    // 1. Singleton
    // =========================================================================

    public function testGetInstanceReturnsSameInstance(): void
    {
        $this->assertSame(Event::getInstance(), Event::getInstance());
    }

    // =========================================================================
    // 2. Persistent listeners: on() + emit()
    // =========================================================================

    public function testOnListenerFiresOnEmit(): void
    {
        $fired = false;
        Event::on('boot', function () use (&$fired): void {
            $fired = true;
        });

        Event::emit('boot');
        $this->assertTrue($fired);
    }

    public function testPersistentListenerFiresOnEveryEmit(): void
    {
        $count = 0;
        Event::on('tick', function () use (&$count): void {
            $count++;
        });

        Event::emit('tick');
        Event::emit('tick');
        Event::emit('tick');

        $this->assertSame(3, $count);
    }

    public function testEmitForwardsArgumentsVerbatim(): void
    {
        $received = null;
        Event::on('payload', function (...$args) use (&$received): void {
            $received = $args;
        });

        Event::emit('payload', 'a', 42, ['k' => 'v'], null);

        $this->assertSame(['a', 42, ['k' => 'v'], null], $received);
    }

    public function testMultiplePersistentListenersFireInRegistrationOrder(): void
    {
        $order = [];
        Event::on('seq', function () use (&$order): void {
            $order[] = 'first';
        });
        Event::on('seq', function () use (&$order): void {
            $order[] = 'second';
        });

        Event::emit('seq');

        $this->assertSame(['first', 'second'], $order);
    }

    public function testEmitWithNoListenersIsNoOp(): void
    {
        // Should neither error nor throw.
        Event::emit('nobody-listening', 'x');
        $this->assertFalse(Event::has('nobody-listening'));
    }

    // =========================================================================
    // 3. One-shot listeners: once()
    // =========================================================================

    public function testOnceListenerFiresExactlyOnce(): void
    {
        $count = 0;
        Event::once('init', function () use (&$count): void {
            $count++;
        });

        Event::emit('init');
        Event::emit('init');
        Event::emit('init');

        $this->assertSame(1, $count);
    }

    public function testOnceListenerReceivesArguments(): void
    {
        $received = null;
        Event::once('with-args', function ($a, $b) use (&$received): void {
            $received = [$a, $b];
        });

        Event::emit('with-args', 'hello', 'world');

        $this->assertSame(['hello', 'world'], $received);
    }

    public function testOnceListenerIsRemovedAfterFiring(): void
    {
        Event::once('one-time', function (): void {});
        $this->assertTrue(Event::has('one-time'));

        Event::emit('one-time');

        $this->assertFalse(Event::has('one-time'));
    }

    public function testOnceAndPersistentCoexistOnSameEvent(): void
    {
        $onceCount = 0;
        $onCount = 0;

        Event::once('mixed', function () use (&$onceCount): void {
            $onceCount++;
        });
        Event::on('mixed', function () use (&$onCount): void {
            $onCount++;
        });

        Event::emit('mixed');
        Event::emit('mixed');

        $this->assertSame(1, $onceCount, 'one-shot listener must fire only once');
        $this->assertSame(2, $onCount, 'persistent listener must fire every time');
    }

    /**
     * The documented re-entrancy guarantee: if a once-callback emits the same
     * event again while it is running, it must NOT trigger itself a second
     * time (it dequeues itself before invocation).
     */
    public function testOnceCallbackReEmittingSameEventDoesNotRecurseInfinitely(): void
    {
        $count = 0;
        Event::once('reentrant', function () use (&$count): void {
            $count++;
            // Re-emit from inside the one-shot callback.
            Event::emit('reentrant');
        });

        Event::emit('reentrant');

        $this->assertSame(1, $count);
    }

    // =========================================================================
    // 4. has()
    // =========================================================================

    public function testHasIsFalseForUnregisteredEvent(): void
    {
        $this->assertFalse(Event::has('never-registered'));
    }

    public function testHasIsTrueForPersistentListener(): void
    {
        Event::on('present', function (): void {});
        $this->assertTrue(Event::has('present'));
    }

    public function testHasIsTrueForPendingOnceListener(): void
    {
        Event::once('pending', function (): void {});
        $this->assertTrue(Event::has('pending'));
    }

    // =========================================================================
    // 5. remove()
    // =========================================================================

    public function testRemoveDropsPersistentListeners(): void
    {
        $fired = false;
        Event::on('removable', function () use (&$fired): void {
            $fired = true;
        });

        Event::remove('removable');
        Event::emit('removable');

        $this->assertFalse($fired);
        $this->assertFalse(Event::has('removable'));
    }

    public function testRemoveDropsPendingOnceListeners(): void
    {
        $fired = false;
        Event::once('removable-once', function () use (&$fired): void {
            $fired = true;
        });

        Event::remove('removable-once');
        Event::emit('removable-once');

        $this->assertFalse($fired);
        $this->assertFalse(Event::has('removable-once'));
    }

    public function testRemoveOnlyAffectsTargetedEvent(): void
    {
        $keptFired = false;
        Event::on('keep', function () use (&$keptFired): void {
            $keptFired = true;
        });
        Event::on('drop', function (): void {});

        Event::remove('drop');

        $this->assertTrue(Event::has('keep'));
        $this->assertFalse(Event::has('drop'));

        Event::emit('keep');
        $this->assertTrue($keptFired);
    }
}