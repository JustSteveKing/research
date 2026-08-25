<?php

namespace App\Enums;

enum QuestionOrigin: string
{
    case Gap = 'gap';
    case Ollama = 'ollama';
    case User = 'user';
}
