<?php

namespace App\Enum;

enum NotificationType: string
{
    case RunSucceeded = 'run_succeeded';
    case RunFailed = 'run_failed';
    case DocumentIndexed = 'document_indexed';
    case DocumentFailed = 'document_failed';
    case System = 'system';
}
