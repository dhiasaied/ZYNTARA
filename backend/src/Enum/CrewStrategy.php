<?php

namespace App\Enum;

enum CrewStrategy: string
{
    /** A supervisor LLM decides which member acts next until the goal is met. */
    case Supervisor = 'supervisor';
    /** Members act one after another, each receiving the previous member's output. */
    case Sequential = 'sequential';
}
