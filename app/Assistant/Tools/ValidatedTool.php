<?php

namespace App\Assistant\Tools;

use Illuminate\Support\Facades\Validator;

/**
 * Validates the arguments against a schema of Laravel rules and hands the tool ONLY
 * what those rules mention. That is what makes a forged argument (a client_id the model
 * invented, or was told to use) harmless by construction: it is dropped here, and the
 * tool would not read it anyway.
 */
abstract class ValidatedTool implements AssistantTool
{
    /**
     * @return array<string, mixed>
     */
    abstract protected function rules(): array;

    /**
     * @param array<string, mixed> $arguments already validated, and only the declared keys
     * @return array<string, mixed>
     */
    abstract protected function handle(ToolContext $context, array $arguments): array;

    public function execute(ToolContext $context, array $arguments): array
    {
        $validator = Validator::make($arguments, $this->rules());

        if ($validator->fails()) {
            throw new InvalidToolArguments(implode(' ', $validator->errors()->all()));
        }

        return $this->handle($context, $validator->validated());
    }
}
