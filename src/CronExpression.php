<?php

declare(strict_types=1);

namespace Webrium;

/**
 * Minimal standard 5-field cron expression parser and matcher
 * (minute hour day-of-month month day-of-week).
 *
 * Supports the common syntax: "*", "* /n" steps, "a-b" ranges, "a-b/n"
 * stepped ranges, and "a,b,c" lists, in any combination per field.
 *
 * Day-of-week accepts both 0 and 7 for Sunday, matching cron convention.
 */
class CronExpression
{
    private const FIELD_RANGES = [
        ['min' => 0, 'max' => 59], // minute
        ['min' => 0, 'max' => 23], // hour
        ['min' => 1, 'max' => 31], // day of month
        ['min' => 1, 'max' => 12], // month
        ['min' => 0, 'max' => 7],  // day of week (0 and 7 both = Sunday)
    ];

    /** @var array<int, int[]> Expanded valid values per field, in field order. */
    private array $fields;

    public function __construct(string $expression)
    {
        $parts = preg_split('/\s+/', trim($expression));

        if ($parts === false || count($parts) !== 5) {
            throw new \InvalidArgumentException(
                "Invalid cron expression '$expression': expected 5 space-separated fields (minute hour day month weekday)."
            );
        }

        $this->fields = [];
        foreach ($parts as $index => $part) {
            $range = self::FIELD_RANGES[$index];
            $this->fields[$index] = self::expandField($part, $range['min'], $range['max']);
        }

        // Day-of-week: normalize 7 to 0 so both mean Sunday.
        $this->fields[4] = array_values(array_unique(array_map(
            fn (int $day) => $day === 7 ? 0 : $day,
            $this->fields[4]
        )));
    }

    public function isDue(\DateTimeInterface $at): bool
    {
        return in_array((int) $at->format('i'), $this->fields[0], true)
            && in_array((int) $at->format('G'), $this->fields[1], true)
            && in_array((int) $at->format('j'), $this->fields[2], true)
            && in_array((int) $at->format('n'), $this->fields[3], true)
            && in_array((int) $at->format('w'), $this->fields[4], true);
    }

    /**
     * @return int[]
     */
    private static function expandField(string $field, int $min, int $max): array
    {
        $values = [];

        foreach (explode(',', $field) as $part) {
            $values = array_merge($values, self::expandPart($part, $min, $max));
        }

        return array_values(array_unique($values));
    }

    /**
     * @return int[]
     */
    private static function expandPart(string $part, int $min, int $max): array
    {
        $step = 1;

        if (str_contains($part, '/')) {
            [$part, $stepPart] = explode('/', $part, 2);
            if (!ctype_digit($stepPart) || (int) $stepPart < 1) {
                throw new \InvalidArgumentException("Invalid cron step '$stepPart' in field part '$part/$stepPart'.");
            }
            $step = (int) $stepPart;
        }

        if ($part === '*') {
            [$rangeMin, $rangeMax] = [$min, $max];
        } elseif (str_contains($part, '-')) {
            $bounds = explode('-', $part, 2);
            if (!ctype_digit($bounds[0]) || !ctype_digit($bounds[1])) {
                throw new \InvalidArgumentException("Invalid cron range '$part'.");
            }
            [$rangeMin, $rangeMax] = array_map('intval', $bounds);
        } elseif (ctype_digit($part)) {
            $rangeMin = $rangeMax = (int) $part;
        } else {
            throw new \InvalidArgumentException("Invalid cron field value '$part'.");
        }

        if ($rangeMin < $min || $rangeMax > $max || $rangeMin > $rangeMax) {
            throw new \InvalidArgumentException("Cron field value '$part' is out of range ($min-$max).");
        }

        $values = [];
        for ($i = $rangeMin; $i <= $rangeMax; $i += $step) {
            $values[] = $i;
        }

        return $values;
    }
}
