<?php

namespace App\Enums;

enum KnowledgeState: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Distilling = 'distilling';
    case Checking = 'checking';
    case Planning = 'planning';
    case Writing = 'writing';
    case Mapping = 'mapping';
    case Failed = 'failed';

    public function isWorking(): bool
    {
        return in_array($this, [self::Queued, self::Distilling, self::Checking, self::Planning, self::Writing, self::Mapping], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Idle => 'Idle',
            self::Queued => 'Queued',
            self::Distilling => 'Distilling sources',
            self::Checking => 'Checking for contradictions',
            self::Planning => 'Planning follow-ups',
            self::Writing => 'Writing brief',
            self::Mapping => 'Mapping coverage',
            self::Failed => 'Stalled',
        };
    }
}
