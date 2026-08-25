<?php

namespace App\Enums;

enum ChatRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
