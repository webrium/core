<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webrium\CronExpression;

/**
 * Unit Tests for Webrium\CronExpression
 *
 * Coverage:
 *  - "*" wildcard fields
 *  - fixed values
 *  - ranges ("a-b")
 *  - steps ("* /n", "a-b/n")
 *  - lists ("a,b,c")
 *  - day-of-week 0/7 both meaning Sunday
 *  - invalid expressions
 */
class CronExpressionTest extends TestCase
{
    private function dt(string $dateTime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dateTime);
    }

    public function testEveryMinuteMatchesAnyTime(): void
    {
        $cron = new CronExpression('* * * * *');
        $this->assertTrue($cron->isDue($this->dt('2024-01-01 00:00:00')));
        $this->assertTrue($cron->isDue($this->dt('2024-06-15 23:59:00')));
    }

    public function testFixedMinuteOnlyMatchesThatMinute(): void
    {
        $cron = new CronExpression('30 * * * *');
        $this->assertTrue($cron->isDue($this->dt('2024-01-01 10:30:00')));
        $this->assertFalse($cron->isDue($this->dt('2024-01-01 10:31:00')));
    }

    public function testStepMatchesEveryNMinutes(): void
    {
        $cron = new CronExpression('*/5 * * * *');
        $this->assertTrue($cron->isDue($this->dt('2024-01-01 00:00:00')));
        $this->assertTrue($cron->isDue($this->dt('2024-01-01 00:05:00')));
        $this->assertTrue($cron->isDue($this->dt('2024-01-01 00:10:00')));
        $this->assertFalse($cron->isDue($this->dt('2024-01-01 00:07:00')));
    }

    public function testRangeMatchesWithinBounds(): void
    {
        $cron = new CronExpression('0 9-17 * * *');
        $this->assertTrue($cron->isDue($this->dt('2024-01-01 09:00:00')));
        $this->assertTrue($cron->isDue($this->dt('2024-01-01 17:00:00')));
        $this->assertFalse($cron->isDue($this->dt('2024-01-01 08:00:00')));
        $this->assertFalse($cron->isDue($this->dt('2024-01-01 18:00:00')));
    }

    public function testSteppedRangeMatchesEveryNWithinBounds(): void
    {
        $cron = new CronExpression('0-30/10 * * * *');
        $this->assertTrue($cron->isDue($this->dt('2024-01-01 00:00:00')));
        $this->assertTrue($cron->isDue($this->dt('2024-01-01 00:10:00')));
        $this->assertTrue($cron->isDue($this->dt('2024-01-01 00:20:00')));
        $this->assertTrue($cron->isDue($this->dt('2024-01-01 00:30:00')));
        $this->assertFalse($cron->isDue($this->dt('2024-01-01 00:40:00')));
    }

    public function testListMatchesAnyListedValue(): void
    {
        $cron = new CronExpression('0,15,30,45 * * * *');
        $this->assertTrue($cron->isDue($this->dt('2024-01-01 00:15:00')));
        $this->assertTrue($cron->isDue($this->dt('2024-01-01 00:45:00')));
        $this->assertFalse($cron->isDue($this->dt('2024-01-01 00:20:00')));
    }

    public function testAllFieldsMustMatchSimultaneously(): void
    {
        $cron = new CronExpression('30 14 1 6 *');
        $this->assertTrue($cron->isDue($this->dt('2024-06-01 14:30:00')));
        $this->assertFalse($cron->isDue($this->dt('2024-06-01 14:31:00')));
        $this->assertFalse($cron->isDue($this->dt('2024-06-02 14:30:00')));
        $this->assertFalse($cron->isDue($this->dt('2024-07-01 14:30:00')));
    }

    // =========================================================================
    // Day-of-month / day-of-week interaction (standard cron semantics)
    // =========================================================================

    /**
     * Standard cron: when BOTH day-of-month and day-of-week are restricted
     * (neither is "*"), the day matches if EITHER one does (OR) — e.g.
     * "0 9 1 * 1" means 9am on the 1st of the month OR every Monday, not
     * only on the rare day that happens to be both at once.
     */
    public function testDayOfMonthAndDayOfWeekAreOredWhenBothRestricted(): void
    {
        $cron = new CronExpression('0 9 1 * 1');

        // 1st of the month, a Thursday: matches via day-of-month alone.
        $this->assertTrue($cron->isDue($this->dt('2026-10-01 09:00:00')));

        // A Monday that isn't the 1st: matches via day-of-week alone.
        $this->assertTrue($cron->isDue($this->dt('2026-10-05 09:00:00')));

        // Both at once: OR is inclusive, still matches.
        $this->assertTrue($cron->isDue($this->dt('2026-06-01 09:00:00')));

        // Neither (a Friday that isn't the 1st): no match.
        $this->assertFalse($cron->isDue($this->dt('2026-10-02 09:00:00')));
    }

    public function testDayOfMonthAloneStillActsAsAndWhenDayOfWeekIsWildcard(): void
    {
        $cron = new CronExpression('0 0 1 * *');
        $this->assertTrue($cron->isDue($this->dt('2024-06-01 00:00:00')));
        $this->assertFalse($cron->isDue($this->dt('2024-06-02 00:00:00')));
    }

    public function testDayOfWeekAloneStillActsAsAndWhenDayOfMonthIsWildcard(): void
    {
        $cron = new CronExpression('0 0 * * 1');
        // 2024-01-08 is a Monday, 2024-01-09 a Tuesday.
        $this->assertTrue($cron->isDue($this->dt('2024-01-08 00:00:00')));
        $this->assertFalse($cron->isDue($this->dt('2024-01-09 00:00:00')));
    }

    /**
     * A step field (e.g. every-2-days) is not the literal "*" wildcard, so
     * it still counts as "restricted" and triggers the OR rule against a
     * restricted day-of-week — matching standard cron, which looks at the
     * literal field text, not whether it happens to cover every value.
     */
    public function testSteppedDayOfMonthCountsAsRestrictedForOrLogic(): void
    {
        $cron = new CronExpression('0 0 */2 * 1');

        // 2024-01-08: day 8 (even, not in the odd 1,3,5... every-2 sequence)
        // but a Monday — matches via day-of-week (OR).
        $this->assertTrue($cron->isDue($this->dt('2024-01-08 00:00:00')));

        // 2024-01-02: day 2 (even, no match) and a Tuesday (no match either).
        $this->assertFalse($cron->isDue($this->dt('2024-01-02 00:00:00')));
    }

    public function testDayOfWeekZeroMeansSunday(): void
    {
        $cron = new CronExpression('0 0 * * 0');
        // 2024-01-07 is a Sunday.
        $this->assertTrue($cron->isDue($this->dt('2024-01-07 00:00:00')));
        $this->assertFalse($cron->isDue($this->dt('2024-01-08 00:00:00')));
    }

    public function testDayOfWeekSevenAlsoMeansSunday(): void
    {
        $cron = new CronExpression('0 0 * * 7');
        $this->assertTrue($cron->isDue($this->dt('2024-01-07 00:00:00')));
    }

    public function testThrowsOnWrongFieldCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CronExpression('* * *');
    }

    public function testThrowsOnOutOfRangeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CronExpression('60 * * * *');
    }

    public function testThrowsOnInvertedRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CronExpression('30-10 * * * *');
    }

    public function testThrowsOnNonNumericValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CronExpression('abc * * * *');
    }

    // =========================================================================
    // nextRunDate()
    // =========================================================================

    public function testNextRunDateForEveryMinuteIsOneMinuteAhead(): void
    {
        $cron = new CronExpression('* * * * *');
        $next = $cron->nextRunDate($this->dt('2024-01-01 10:00:30'));
        $this->assertSame('2024-01-01 10:01:00', $next->format('Y-m-d H:i:s'));
    }

    public function testNextRunDateSkipsToNextMatchingStep(): void
    {
        $cron = new CronExpression('*/15 * * * *');
        $next = $cron->nextRunDate($this->dt('2024-01-01 10:01:00'));
        $this->assertSame('2024-01-01 10:15:00', $next->format('Y-m-d H:i:s'));
    }

    public function testNextRunDateCrossesDayBoundary(): void
    {
        $cron = new CronExpression('0 0 * * *');
        $next = $cron->nextRunDate($this->dt('2024-01-01 23:59:00'));
        $this->assertSame('2024-01-02 00:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function testNextRunDateCrossesMonthBoundaryForMonthlySchedule(): void
    {
        $cron = new CronExpression('0 0 1 * *');
        $next = $cron->nextRunDate($this->dt('2024-01-15 12:00:00'));
        $this->assertSame('2024-02-01 00:00:00', $next->format('Y-m-d H:i:s'));
    }

    /**
     * A candidate is never due at the reference instant itself, even when
     * it exactly matches — nextRunDate() always looks strictly forward.
     */
    public function testNextRunDateNeverReturnsTheReferenceInstantItself(): void
    {
        $cron = new CronExpression('0 0 * * *');
        $next = $cron->nextRunDate($this->dt('2024-01-02 00:00:00'));
        $this->assertSame('2024-01-03 00:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function testNextRunDateDefaultsToNowWhenNoReferenceGiven(): void
    {
        $cron = new CronExpression('* * * * *');
        $next = $cron->nextRunDate();
        $this->assertGreaterThan(new \DateTimeImmutable(), $next);
    }
}
