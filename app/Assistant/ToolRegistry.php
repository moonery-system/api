<?php

namespace App\Assistant;

use App\Assistant\Llm\ToolCall;
use App\Assistant\Llm\ToolDefinition;
use App\Assistant\Llm\ToolResult;
use App\Assistant\Tools\AssistantTool;
use App\Assistant\Tools\InvalidToolArguments;
use App\Assistant\Tools\ToolContext;
use Illuminate\Support\Facades\Log;

/**
 * The only door between the model and the system. Whatever goes wrong in a tool comes
 * back to the model as an error result -- it never escapes as an exception, and it never
 * carries an internal message.
 */
class ToolRegistry
{
    // A result bigger than this is not handed to the model at all.
    private const MAX_RESULT_CHARS = 6000;

    /** @var array<string, AssistantTool> */
    private array $tools = [];

    /**
     * @param iterable<AssistantTool> $tools
     */
    public function __construct(iterable $tools)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->definition()->name] = $tool;
        }
    }

    /**
     * @return array<int, ToolDefinition>
     */
    public function definitions(): array
    {
        return array_values(array_map(fn(AssistantTool $tool) => $tool->definition(), $this->tools));
    }

    public function execute(ToolCall $call, ToolContext $context): ToolResult
    {
        $tool = $this->tools[$call->name] ?? null;

        if (!$tool) {
            return $this->error($call, "There is no tool called '{$call->name}'.");
        }

        try {
            $content = $tool->execute($context, $call->arguments);
        } catch (InvalidToolArguments $e) {
            return $this->error($call, 'Invalid arguments: ' . $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Assistant tool failed', ['tool' => $call->name, 'message' => $e->getMessage()]);

            return $this->error($call, 'The tool failed. Do not retry; hand over to support if you cannot answer.');
        }

        if (strlen((string) json_encode($content)) > self::MAX_RESULT_CHARS) {
            return $this->error($call, 'The result was too large to return. Ask for something narrower.');
        }

        return new ToolResult($call->id, $call->name, $content);
    }

    private function error(ToolCall $call, string $message): ToolResult
    {
        return new ToolResult($call->id, $call->name, ['message' => $message], isError: true);
    }
}
