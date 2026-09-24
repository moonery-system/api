<?php

namespace App\Assistant\Llm;

/**
 * Provider-neutral contract of a language model (Strategy).
 *
 * Implementations only translate this format to and from their provider's API. The
 * tool loop -- call the model, run the tools it asked for, hand the results back --
 * is ours and lives in AssistantRunner, so a new provider never re-implements it.
 */
interface LlmClient
{
    public function generate(LlmRequest $request): LlmResponse;

    public function provider(): string;

    public function model(): string;
}
