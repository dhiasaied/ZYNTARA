<?php

namespace App\Service;

/**
 * Starting points for agents, one per rung / business activity shown in the video
 * (email app, shop, education, research crew members).
 */
final class TemplateCatalog
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return [
            [
                'id' => 'email-assistant',
                'name' => 'Email assistant',
                'level' => 'tools',
                'vertical' => 'email',
                'description' => 'The "AI app" from the video: drafts and sends professional emails for you.',
                'systemPrompt' => "You are an email assistant. Write clear, polite and concise emails.\nAlways confirm the recipient, subject and body. When the user explicitly asks you to send, use the send_email tool.",
                'tools' => ['send_email', 'current_datetime'],
                'autonomous' => false,
            ],
            [
                'id' => 'shop-support',
                'name' => 'Shop support agent',
                'level' => 'rag',
                'vertical' => 'ecommerce',
                'description' => 'Answers customers from your policies (RAG) and looks up orders in the database.',
                'systemPrompt' => "You are the customer support agent of an online shop.\nAnswer ONLY from the knowledge base and the database. If the answer is not there, say you don't know and offer to escalate.\nNever invent order numbers, prices or policies.",
                'tools' => ['database_query', 'send_email'],
                'autonomous' => false,
            ],
            [
                'id' => 'course-tutor',
                'name' => 'Course tutor',
                'level' => 'rag',
                'vertical' => 'education',
                'description' => 'Teaches from your course material and quizzes the learner.',
                'systemPrompt' => "You are a patient tutor. Explain using only the course documents in the knowledge base, step by step, with short examples.\nEnd every answer with one quick question to check understanding.",
                'tools' => ['calculator'],
                'autonomous' => false,
            ],
            [
                'id' => 'research-agent',
                'name' => 'Research agent',
                'level' => 'agent',
                'vertical' => 'research',
                'description' => 'Autonomous: plans, searches the web, verifies and writes a sourced brief.',
                'systemPrompt' => "You are an autonomous research agent. Break the task into steps, search for evidence, cross-check facts and produce a concise brief with sources.",
                'tools' => ['web_search', 'calculator', 'current_datetime'],
                'autonomous' => true,
            ],
            [
                'id' => 'reviewer',
                'name' => 'Reviewer',
                'level' => 'app',
                'vertical' => 'generic',
                'description' => 'Critically reviews another agent\'s work for accuracy, gaps and clarity.',
                'systemPrompt' => "You are a strict reviewer. Check the previous work for factual errors, missing steps and unclear wording. Return the corrected final version.",
                'tools' => [],
                'autonomous' => false,
            ],
            [
                'id' => 'coder',
                'name' => 'Coder',
                'level' => 'app',
                'vertical' => 'generic',
                'description' => 'Turns requirements into clean, commented code.',
                'systemPrompt' => "You are a senior software engineer. Produce correct, minimal, well-commented code and explain how to run it.",
                'tools' => ['calculator'],
                'autonomous' => false,
            ],
        ];
    }
}
