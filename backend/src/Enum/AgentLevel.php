<?php

namespace App\Enum;

/** The rungs of the ladder demonstrated in the reference video. */
enum AgentLevel: string
{
    case Prompt = 'prompt';
    case App = 'app';
    case Tools = 'tools';
    case Rag = 'rag';
    case Agent = 'agent';
    case Agentic = 'agentic';

    public function rank(): int
    {
        return match ($this) {
            self::Prompt => 1,
            self::App => 2,
            self::Tools => 3,
            self::Rag => 4,
            self::Agent => 5,
            self::Agentic => 6,
        };
    }
}
