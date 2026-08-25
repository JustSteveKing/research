<?php

namespace App\Enums;

enum RunStatus: string
{
    case Queued = 'queued';
    case Streaming = 'streaming';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }
}
