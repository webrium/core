<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webrium\App;
use Webrium\Directory;
use Webrium\Schedule;
use Webrium\ScheduleEvent;

/**
 * Unit Tests for Webrium\Schedule / Webrium\ScheduleEvent / Webrium\ScheduleLock
 *
 * Coverage:
 *  - ScheduleEvent fluent interval helpers produce the expected cron expression
 *  - name() / lockKey() (explicit name, and stable inference for string/array/closure callbacks)
 *  - Schedule::call()/all()/reset() registry behavior
 *  - loadFromDirectory(): recursive discovery, per-file error isolation
 *  - runDue(): only due tasks run, per-task failure isolation, overlap lock (skipped)
 */
class ScheduleTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/webrium_schedule_test_' . uniqid();
        mkdir($this->root, 0755, true);
        App::setRootPath($this->root);
        Directory::initDefaultStructure();
        Schedule::reset();
    }

    protected function tearDown(): void
    {
        Schedule::reset();
        $this->removeDir($this->root);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = "$dir/$item";
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    // =========================================================================
    // 1. ScheduleEvent fluent interval helpers
    // =========================================================================

    public function testEveryMinute(): void
    {
        $this->assertSame('* * * * *', (new ScheduleEvent(fn () => null))->everyMinute()->getExpression());
    }

    public function testEveryFiveMinutes(): void
    {
        $this->assertSame('*/5 * * * *', (new ScheduleEvent(fn () => null))->everyFiveMinutes()->getExpression());
    }

    public function testHourlyAt(): void
    {
        $this->assertSame('15 * * * *', (new ScheduleEvent(fn () => null))->hourlyAt(15)->getExpression());
    }

    public function testDailyAtParsesHourAndMinute(): void
    {
        $this->assertSame('30 13 * * *', (new ScheduleEvent(fn () => null))->dailyAt('13:30')->getExpression());
    }

    public function testDailyAtDefaultsMinuteWhenOmitted(): void
    {
        $this->assertSame('0 9 * * *', (new ScheduleEvent(fn () => null))->dailyAt('9')->getExpression());
    }

    public function testWeeklyOnSpecificDay(): void
    {
        $this->assertSame('0 8 * * 3', (new ScheduleEvent(fn () => null))->weeklyOn(3, '08:00')->getExpression());
    }

    public function testMonthly(): void
    {
        $this->assertSame('0 0 1 * *', (new ScheduleEvent(fn () => null))->monthly()->getExpression());
    }

    public function testExplicitCronExpressionOverridesFluentHelpers(): void
    {
        $event = (new ScheduleEvent(fn () => null))->everyMinute()->cron('0 3 * * *');
        $this->assertSame('0 3 * * *', $event->getExpression());
    }

    public function testNextRunDateDelegatesToTheUnderlyingCronExpression(): void
    {
        $event = (new ScheduleEvent(fn () => null))->dailyAt('03:00');
        $next  = $event->nextRunDate(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $this->assertSame('2024-01-02 03:00:00', $next->format('Y-m-d H:i:s'));
    }

    // =========================================================================
    // 2. name() / lockKey()
    // =========================================================================

    public function testExplicitNameIsReturnedVerbatim(): void
    {
        $event = (new ScheduleEvent(fn () => null))->name('email.send-queued');
        $this->assertSame('email.send-queued', $event->getName());
    }

    public function testStringCallbackNameIsInferredFromCallbackItself(): void
    {
        $event = new ScheduleEvent('App\\Services\\Reports@daily');
        $this->assertSame('App\\Services\\Reports@daily', $event->getName());
    }

    public function testArrayCallbackNameIsInferredFromClassAndMethod(): void
    {
        $event = new ScheduleEvent([\Tests\ScheduleTestTarget::class, 'ok']);
        $this->assertSame(\Tests\ScheduleTestTarget::class . '::ok', $event->getName());
    }

    public function testClosureNameIsStableAcrossCallsForTheSameInstance(): void
    {
        $event = new ScheduleEvent(fn () => null);
        $this->assertSame($event->getName(), $event->getName());
    }

    public function testLockKeyIsFilesystemSafe(): void
    {
        $event = (new ScheduleEvent(fn () => null))->name('weird name / with * chars');
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_.-]+$/', $event->lockKey());
    }

    // =========================================================================
    // 3. Schedule registry
    // =========================================================================

    public function testCallRegistersEventAndReturnsIt(): void
    {
        $event = Schedule::call(fn () => null);
        $this->assertInstanceOf(ScheduleEvent::class, $event);
        $this->assertSame([$event], Schedule::all());
    }

    public function testResetClearsRegistry(): void
    {
        Schedule::call(fn () => null);
        Schedule::reset();
        $this->assertSame([], Schedule::all());
    }

    public function testFindReturnsRegisteredEventByName(): void
    {
        $event = Schedule::call(fn () => null)->name('reports.daily');
        $this->assertSame($event, Schedule::find('reports.daily'));
    }

    public function testFindReturnsNullForUnknownName(): void
    {
        $this->assertNull(Schedule::find('does.not.exist'));
    }

    /**
     * Schedule::run() executes a task immediately regardless of whether it
     * is actually due — the mechanism behind an on-demand `schedule:test`
     * command — while still going through the same lock/error isolation
     * as runDue().
     */
    public function testRunExecutesATaskImmediatelyIgnoringItsDueCheck(): void
    {
        $ran = false;
        // Scheduled for 03:00 daily; "now" is irrelevant to Schedule::run().
        $event = Schedule::call(function () use (&$ran) { $ran = true; })
            ->name('manual-trigger')->dailyAt('03:00');

        $result = Schedule::run($event);

        $this->assertTrue($ran);
        $this->assertSame(['name' => 'manual-trigger', 'status' => 'ran', 'error' => null], $result);
    }

    // =========================================================================
    // 4. Callback resolution: [Class::class, 'method'], 'Class@method',
    //    static vs. instance methods, and constructor dependencies
    // =========================================================================

    /**
     * [ClassName::class, 'method'] with a *non-static* method used to fail
     * (is_callable() is false for a class-string paired with an instance
     * method — there's no instance to call it on). It's now instantiated
     * the same way 'Class@method' already was.
     */
    public function testArrayFormWithClassStringWorksForNonStaticMethod(): void
    {
        $event = Schedule::call([ScheduleTestTarget::class, 'ok'])->name('array-non-static');
        $result = Schedule::run($event);

        $this->assertSame(['name' => 'array-non-static', 'status' => 'ran', 'error' => null], $result);
    }

    public function testArrayFormWithClassStringStillWorksForStaticMethod(): void
    {
        $event = Schedule::call([ScheduleTestStaticTarget::class, 'ok'])->name('array-static');
        $result = Schedule::run($event);

        $this->assertSame(['name' => 'array-static', 'status' => 'ran', 'error' => null], $result);
    }

    public function testAtSyntaxStringFormStillWorksForNonStaticMethod(): void
    {
        $event = Schedule::call(\Tests\ScheduleTestTarget::class . '@ok')->name('at-syntax');
        $result = Schedule::run($event);

        $this->assertSame(['name' => 'at-syntax', 'status' => 'ran', 'error' => null], $result);
    }

    /**
     * There is no dependency container here, so a class-name callback can
     * only be instantiated with no constructor arguments. That failure
     * mode must surface as a clear, actionable error — not a raw
     * ArgumentCountError leaking out of `new $class()`.
     */
    public function testClassWithRequiredConstructorArgumentsFailsWithActionableMessage(): void
    {
        $event = Schedule::call([ScheduleTestNeedsDependency::class, 'run'])->name('needs-dependency');
        $result = Schedule::run($event);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('requires constructor arguments', $result['error']);
        $this->assertStringContainsString('ScheduleTestNeedsDependency', $result['error']);
    }

    /**
     * The documented workaround for the case above: build the instance
     * yourself and register it directly.
     */
    public function testPreBuiltInstanceBypassesTheConstructorLimitation(): void
    {
        $service = new ScheduleTestNeedsDependency('a-real-dependency');
        $event = Schedule::call([$service, 'run'])->name('pre-built-instance');
        $result = Schedule::run($event);

        $this->assertSame(['name' => 'pre-built-instance', 'status' => 'ran', 'error' => null], $result);
    }

    // =========================================================================
    // 5. loadFromDirectory(): discovery + per-file error isolation
    // =========================================================================

    public function testLoadFromDirectoryRegistersTasksFromEveryFile(): void
    {
        $dir = $this->root . '/Schedules';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/One.php', '<?php \\Webrium\\Schedule::call(fn () => null)->name("one");');
        file_put_contents($dir . '/Two.php', '<?php \\Webrium\\Schedule::call(fn () => null)->name("two");');

        $errors = Schedule::loadFromDirectory($dir);

        $this->assertSame([], $errors);
        $this->assertCount(2, Schedule::all());
    }

    public function testLoadFromDirectoryIsRecursiveForPluginSubfolders(): void
    {
        $dir = $this->root . '/Schedules';
        mkdir($dir . '/some-plugin', 0755, true);
        file_put_contents($dir . '/some-plugin/Task.php', '<?php \\Webrium\\Schedule::call(fn () => null);');

        Schedule::loadFromDirectory($dir);

        $this->assertCount(1, Schedule::all());
    }

    public function testLoadFromDirectoryReturnsEmptyArrayForMissingDirectory(): void
    {
        $this->assertSame([], Schedule::loadFromDirectory($this->root . '/does-not-exist'));
    }

    /**
     * A broken task file (throws while being registered) must not prevent a
     * different, valid file from registering its own task.
     */
    public function testABrokenFileDoesNotPreventOtherFilesFromLoading(): void
    {
        $dir = $this->root . '/Schedules';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/Broken.php', '<?php throw new \\RuntimeException("boom while loading");');
        file_put_contents($dir . '/Good.php', '<?php \\Webrium\\Schedule::call(fn () => null)->name("good");');

        $errors = Schedule::loadFromDirectory($dir);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Broken.php', $errors[0]['file']);
        $this->assertStringContainsString('boom while loading', $errors[0]['error']);

        $this->assertCount(1, Schedule::all());
        $this->assertSame('good', Schedule::all()[0]->getName());
    }

    // =========================================================================
    // 6. runDue(): due-filtering + per-task failure isolation + overlap lock
    // =========================================================================

    public function testRunDueOnlyRunsTasksThatAreDueAtGivenTime(): void
    {
        $ranFive = false;
        $ranHourly = false;

        Schedule::call(function () use (&$ranFive) { $ranFive = true; })->name('five')->everyFiveMinutes();
        Schedule::call(function () use (&$ranHourly) { $ranHourly = true; })->name('hourly')->hourlyAt(0);

        Schedule::runDue(new \DateTimeImmutable('2024-01-01 10:05:00'));

        $this->assertTrue($ranFive);
        $this->assertFalse($ranHourly);
    }

    public function testRunDueReportsRanStatusForSuccessfulTask(): void
    {
        Schedule::call(fn () => null)->name('ok-task')->everyMinute();

        $report = Schedule::runDue(new \DateTimeImmutable('2024-01-01 00:00:00'));

        $this->assertSame([['name' => 'ok-task', 'status' => 'ran', 'error' => null]], $report);
    }

    /**
     * SECURITY/RELIABILITY: one task throwing must not stop other due tasks
     * from running, and must be reported as 'failed', not silently swallowed.
     */
    public function testAFailingTaskDoesNotPreventOtherDueTasksFromRunning(): void
    {
        $secondRan = false;

        Schedule::call(function () { throw new \RuntimeException('task blew up'); })
            ->name('failing')->everyMinute();
        Schedule::call(function () use (&$secondRan) { $secondRan = true; })
            ->name('second')->everyMinute();

        $report = Schedule::runDue(new \DateTimeImmutable('2024-01-01 00:00:00'));

        $this->assertTrue($secondRan, 'a later due task must still run after an earlier one fails');

        $byName = array_column($report, null, 'name');
        $this->assertSame('failed', $byName['failing']['status']);
        $this->assertStringContainsString('task blew up', $byName['failing']['error']);
        $this->assertSame('ran', $byName['second']['status']);
    }

    /**
     * Simulates "another process is still running this task" by holding the
     * task's lock open from the test itself before calling runDue() — a real
     * overlap can't be reproduced deterministically in a single-threaded
     * test process, but the lock primitive is exactly what a slow-running
     * task would leave held in production.
     */
    public function testOverlappingRunIsSkippedWhileLockIsHeld(): void
    {
        $ran = false;
        $event = Schedule::call(function () use (&$ran) { $ran = true; })
            ->name('held-task')->everyMinute();

        $externalLock = new \Webrium\ScheduleLock($event->lockKey());
        $this->assertTrue($externalLock->acquire());

        $now = new \DateTimeImmutable('2024-01-01 00:00:00');
        $report = Schedule::runDue($now);

        $this->assertFalse($ran, 'task must not run while its lock is already held elsewhere');
        $this->assertSame('skipped', $report[0]['status']);

        $externalLock->release();

        $report = Schedule::runDue($now);

        $this->assertTrue($ran, 'task must run once the lock is released');
        $this->assertSame('ran', $report[0]['status']);
    }
}

class ScheduleTestTarget
{
    public function ok(): bool
    {
        return true;
    }
}

class ScheduleTestStaticTarget
{
    public static function ok(): bool
    {
        return true;
    }
}

class ScheduleTestNeedsDependency
{
    private string $dependency;

    public function __construct(string $dependency)
    {
        $this->dependency = $dependency;
    }

    public function run(): string
    {
        return $this->dependency;
    }
}
