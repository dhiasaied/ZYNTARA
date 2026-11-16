<?php

namespace App\Enum;

/** Mirrors the ladder: a raw prompt, a single agent (app/tools/rag/agent) or a crew (agentic). */
enum RunKind: string
{
    case Prompt = 'prompt';
    case Agent = 'agent';
    case Crew = 'crew';
}
