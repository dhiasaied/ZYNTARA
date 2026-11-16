<?php

namespace App\Message;

final readonly class ExecuteRun
{
    public function __construct(public string $runId)
    {
    }
}
