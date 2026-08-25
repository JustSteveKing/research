<?php

namespace App\Enums;

/**
 * How often a thread re-researches its own seed question. Cadences are
 * deliberately coarse: web research is slow and paid, and a topic that moves
 * faster than daily is not really a research thread.
 */
enum WatchCadence: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function days(): int
    {
        return match ($this) {
            self::Daily => 1,
            self::Weekly => 7,
            self::Monthly => 30,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }
}
