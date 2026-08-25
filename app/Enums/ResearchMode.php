<?php

namespace App\Enums;

enum ResearchMode: string
{
    case Fast = 'fast';
    case Balanced = 'balanced';

    /**
     * The number of Tabstack credits a single run in this mode costs.
     */
    public function creditCost(): int
    {
        return match ($this) {
            self::Fast => 250,
            self::Balanced => 350,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Fast => 'Fast',
            self::Balanced => 'Balanced',
        };
    }
}
