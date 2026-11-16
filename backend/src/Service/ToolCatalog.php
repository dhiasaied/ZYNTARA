<?php

namespace App\Service;

/**
 * Tools an agent can be given (the TOOLS rung). Keys must match the tool registry of the
 * Python agents service (agents/app/tools/registry.py).
 *
 * knowledge_search, save_memory and recall_memory are not listed: they are attached
 * automatically when an agent has knowledge bases or memory enabled.
 */
final class ToolCatalog
{
    public const KEYS = ['send_email', 'web_search', 'database_query', 'calculator', 'current_datetime'];

    /** @return list<array{key: string, name: string, description: string, icon: string, category: string}> */
    public function all(): array
    {
        return [
            [
                'key' => 'send_email',
                'name' => 'Email',
                'description' => 'Compose and send emails through the configured SMTP server.',
                'icon' => 'mail',
                'category' => 'communication',
            ],
            [
                'key' => 'web_search',
                'name' => 'Web search',
                'description' => 'Search the web for fresh information (Tavily when configured, Wikipedia otherwise).',
                'icon' => 'globe',
                'category' => 'research',
            ],
            [
                'key' => 'database_query',
                'name' => 'Database',
                'description' => 'Run read-only SQL against your business database (products, orders, customers, courses).',
                'icon' => 'database',
                'category' => 'data',
            ],
            [
                'key' => 'calculator',
                'name' => 'Calculator',
                'description' => 'Evaluate arithmetic expressions precisely instead of guessing.',
                'icon' => 'calculator',
                'category' => 'utility',
            ],
            [
                'key' => 'current_datetime',
                'name' => 'Date & time',
                'description' => 'Know the current date and time in any timezone.',
                'icon' => 'clock',
                'category' => 'utility',
            ],
        ];
    }
}
