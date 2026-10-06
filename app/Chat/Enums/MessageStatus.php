<?php

namespace App\Chat\Enums;

enum MessageStatus: string
{
    case Streaming = 'streaming';
    case Completed = 'completed';
    case Failed = 'failed';
    case NoAnswer = 'no_answer';
}
