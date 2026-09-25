<?php

namespace App\Assistant\Tools;

use App\Assistant\Llm\ToolDefinition;

interface AssistantTool
{
    public function definition(): ToolDefinition;

    /**
     * @param array<string, mixed> $arguments what the model wrote: untrusted
     * @return array<string, mixed>
     * @throws InvalidToolArguments
     */
    public function execute(ToolContext $context, array $arguments): array;
}
