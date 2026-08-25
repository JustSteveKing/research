<?php

namespace App\Enums;

enum QuestionStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Done = 'done';
    case Rejected = 'rejected';
}
