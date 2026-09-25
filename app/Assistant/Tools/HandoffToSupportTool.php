<?php

namespace App\Assistant\Tools;

use App\Assistant\Llm\ToolDefinition;

/**
 * Only records the wish. The runner is the one that flips the conversation and speaks,
 * so the handoff happens in one place whether the model asked for it or a limit forced it.
 */
class HandoffToSupportTool extends ValidatedTool
{
    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'handoff_to_support',
            description: 'Hands the conversation over to a human from the support team. Use it when you cannot '
                . 'answer with the data you have, when the customer asks for a person, or when you are in doubt. '
                . 'After it you stop answering in this conversation.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'reason' => ['type' => 'string', 'description' => 'One short sentence: why support is needed.'],
                ],
                'required' => ['reason'],
            ],
        );
    }

    protected function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:300']];
    }

    protected function handle(ToolContext $context, array $arguments): array
    {
        $context->handoffNote = trim($arguments['reason']);

        return [
            'handoff' => 'requested',
            'note' => 'Support will take over. Tell the customer, in one short sentence, that support will continue.',
        ];
    }
}
