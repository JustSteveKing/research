<?php

namespace App\Enums;

enum ThreadStatus: string
{
    case Idle = 'idle';
    case Running = 'running';
    case Paused = 'paused';
    case Complete = 'complete';
    case Failed = 'failed';
}
