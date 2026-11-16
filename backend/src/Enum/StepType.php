<?php

namespace App\Enum;

enum StepType: string
{
    case Plan = 'plan';
    case Thought = 'thought';
    case ToolCall = 'tool_call';
    case ToolResult = 'tool_result';
    case Retrieval = 'retrieval';
    case Handoff = 'handoff';
    case Reflection = 'reflection';
    case Token = 'token';
    case Final = 'final';
    case Error = 'error';
}
