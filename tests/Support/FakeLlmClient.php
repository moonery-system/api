<?php

namespace Tests\Support;

use App\Assistant\Llm\LlmClient;
use App\Assistant\Llm\LlmRequest;
use App\Assistant\Llm\LlmResponse;
use App\Assistant\Llm\ToolCall;
use App\Assistant\Llm\Usage;
use LogicException;
use Throwable;

/**
 * A model with a script: each call to generate() consumes the next step, which is a
 * response to return, a throwable to raise or a closure that receives the request and
 * produces either. Nothing here touches the network.
 */
class FakeLlmClient implements LlmClient
{
    /** @var array<int, LlmResponse|Throwable|callable> */
    private array $script;

    /** @var array<int, LlmRequest> */
    public array $requests = [];

    /**
     * @param array<int, LlmResponse|Throwable|callable> $script
     */
    public function __construct(array $script = [])
    {
        $this->script = $script;
    }

    public static function text(string $text, int $input = 10, int $output = 5): LlmResponse
    {
        return new LlmResponse(text: $text, usage: new Usage($input, $output));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public static function callTool(string $name, array $arguments = [], string $id = 'call-1'): LlmResponse
    {
        return new LlmResponse(
            toolCalls: [new ToolCall($id, $name, $arguments)],
            usage: new Usage(10, 5),
            finishReason: 'tool_calls',
        );
    }

    public function push(LlmResponse|Throwable|callable $step): static
    {
        $this->script[] = $step;

        return $this;
    }

    public function calls(): int
    {
        return count($this->requests);
    }

    public function generate(LlmRequest $request): LlmResponse
    {
        $this->requests[] = $request;

        if ($this->script === []) {
            throw new LogicException('FakeLlmClient was called more times than it was scripted for.');
        }

        $step = array_shift($this->script);

        if (is_callable($step) && !($step instanceof LlmResponse) && !($step instanceof Throwable)) {
            $step = $step($request);
        }

        if ($step instanceof Throwable) throw $step;

        return $step;
    }

    public function provider(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-model';
    }
}
