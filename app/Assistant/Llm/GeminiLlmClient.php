<?php

namespace App\Assistant\Llm;

use App\Assistant\Llm\Exceptions\LlmAuthException;
use App\Assistant\Llm\Exceptions\LlmProviderException;
use App\Assistant\Llm\Exceptions\LlmRateLimitException;
use App\Assistant\Llm\Exceptions\LlmTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini over its REST API (generateContent), with the Laravel Http client and
 * no SDK. It only translates formats: retries, spacing and caps are GuardedLlmClient's.
 *
 * Two rules about the credentials: the key travels in the x-goog-api-key header, never
 * in the URL; and it never reaches a log or an exception message. Its format is not
 * validated -- providers change it, and the provider is the one that knows.
 *
 * Thought signatures: models of the Gemini 3 family attach a signature to the parts of
 * their turn and expect it back. Where exactly it sits is the provider's business, so
 * the raw parts of every model turn are kept in LlmResponse::providerState and sent back
 * unchanged, instead of being rebuilt from the normalised fields.
 */
class GeminiLlmClient implements LlmClient
{
    public function __construct(
        private ?string $apiKey,
        private ?string $model,
        private string $baseUrl,
        private int $timeoutSeconds,
    ) {}

    public function provider(): string
    {
        return 'gemini';
    }

    public function model(): string
    {
        return $this->model ?? '';
    }

    public function generate(LlmRequest $request): LlmResponse
    {
        if (!$this->apiKey) {
            throw new LlmAuthException('GEMINI_API_KEY is not configured.');
        }

        if (!$this->model) {
            throw new LlmProviderException('ASSISTANT_MODEL is not configured.');
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeoutSeconds)
                ->post($this->endpoint(), $this->payload($request));
        } catch (ConnectionException $e) {
            throw new LlmTimeoutException('The Gemini request timed out or could not connect.');
        }

        if ($response->failed()) {
            throw $this->errorFor($response);
        }

        return $this->parse($response);
    }

    private function endpoint(): string
    {
        return rtrim($this->baseUrl, '/') . '/models/' . rawurlencode((string) $this->model) . ':generateContent';
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LlmRequest $request): array
    {
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $request->systemPrompt]]],
            'contents' => $this->contents($request->messages),
            'generationConfig' => ['maxOutputTokens' => $request->maxTokens],
        ];

        if ($request->tools) {
            $payload['tools'] = [[
                'functionDeclarations' => array_map(fn(ToolDefinition $tool) => [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'parameters' => $tool->parameters,
                ], $request->tools),
            ]];

            $payload['toolConfig'] = ['functionCallingConfig' => ['mode' => 'AUTO']];
        }

        return $payload;
    }

    /**
     * @param array<int, LlmMessage> $messages
     * @return array<int, array<string, mixed>>
     */
    private function contents(array $messages): array
    {
        $contents = [];

        foreach ($messages as $message) {
            $contents[] = match ($message->role) {
                LlmMessage::ROLE_USER => ['role' => 'user', 'parts' => [['text' => (string) $message->text]]],
                LlmMessage::ROLE_ASSISTANT => ['role' => 'model', 'parts' => $this->modelParts($message)],
                LlmMessage::ROLE_TOOL => ['role' => 'user', 'parts' => $this->toolResultParts($message)],
                default => throw new LlmProviderException("Unknown message role '{$message->role}'."),
            };
        }

        return $contents;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function modelParts(LlmMessage $message): array
    {
        // The provider's own parts, untouched: they carry the thought signatures.
        if (isset($message->providerState['parts'])) return $message->providerState['parts'];

        $parts = [];

        if ($message->text !== null && $message->text !== '') $parts[] = ['text' => $message->text];

        foreach ($message->toolCalls as $call) {
            $parts[] = ['functionCall' => ['name' => $call->name, 'args' => (object) $call->arguments]];
        }

        return $parts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolResultParts(LlmMessage $message): array
    {
        return array_map(function (ToolResult $result) {
            $functionResponse = [
                'name' => $result->name,
                // Gemini wants an object here, so a list is wrapped.
                'response' => $result->isError
                    ? ['error' => $result->content]
                    : ['result' => $result->content],
            ];

            // Only when the provider gave the call an id do we answer with it.
            if (!str_starts_with($result->callId, 'local-')) $functionResponse['id'] = $result->callId;

            return ['functionResponse' => $functionResponse];
        }, $message->toolResults);
    }

    private function parse(Response $response): LlmResponse
    {
        $candidate = $response->json('candidates.0') ?? [];
        $parts = $candidate['content']['parts'] ?? [];

        $text = '';
        $toolCalls = [];

        foreach ($parts as $index => $part) {
            // A "thought" part is the model's reasoning summary, not something to say to the user.
            if (($part['thought'] ?? false) === true) continue;

            if (isset($part['text'])) $text .= $part['text'];

            if (isset($part['functionCall'])) {
                $call = $part['functionCall'];

                $toolCalls[] = new ToolCall(
                    id: $call['id'] ?? "local-{$index}",
                    name: (string) ($call['name'] ?? ''),
                    arguments: (array) ($call['args'] ?? []),
                );
            }
        }

        $usage = $response->json('usageMetadata') ?? [];
        $input = (int) ($usage['promptTokenCount'] ?? 0);
        // Reasoning tokens are billed as output, so they count as output.
        $output = isset($usage['totalTokenCount'])
            ? max(0, (int) $usage['totalTokenCount'] - $input)
            : (int) ($usage['candidatesTokenCount'] ?? 0);

        return new LlmResponse(
            text: $text === '' ? null : $text,
            toolCalls: $toolCalls,
            usage: new Usage($input, $output),
            finishReason: (string) ($candidate['finishReason'] ?? 'unknown'),
            providerState: $parts ? ['parts' => $this->keepEmptyObjects($parts)] : null,
        );
    }

    /**
     * The parts are decoded as arrays, and an empty JSON object -- what a tool call without
     * arguments carries as "args": {} -- decodes to an empty array, which encodes back as
     * "[]". The provider rejects that ("cannot start list") when the parts are sent back, so
     * the fields that are objects by contract are put back as objects.
     *
     * @param array<int, array<string, mixed>> $parts
     * @return array<int, array<string, mixed>>
     */
    private function keepEmptyObjects(array $parts): array
    {
        foreach ($parts as &$part) {
            if (isset($part['functionCall']) && ($part['functionCall']['args'] ?? null) === []) {
                $part['functionCall']['args'] = new \stdClass();
            }
        }

        return $parts;
    }

    private function errorFor(Response $response): \Throwable
    {
        $status = $response->status();
        $detail = $this->redact((string) ($response->json('error.message') ?? ''));
        $reason = (string) ($response->json('error.status') ?? '');

        $summary = trim("Gemini answered HTTP {$status} {$reason}" . ($detail !== '' ? ": {$detail}" : ''));

        if ($status === 429) {
            return new LlmRateLimitException($summary, $this->retryAfterMs($response));
        }

        if (in_array($status, [400, 401, 403], true)) {
            return new LlmAuthException($summary . ' -- check GEMINI_API_KEY, ASSISTANT_MODEL and the tool schemas.');
        }

        return new LlmProviderException($summary, retryable: $status >= 500);
    }

    /**
     * The delay the provider suggests: the Retry-After header, or the RetryInfo detail of
     * the error body ("33s").
     */
    private function retryAfterMs(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        if ($header !== '' && is_numeric($header)) return (int) round((float) $header * 1000);

        foreach ((array) $response->json('error.details') as $detail) {
            $delay = $detail['retryDelay'] ?? null;

            if (is_string($delay) && preg_match('/^(\d+(?:\.\d+)?)s$/', $delay, $matches)) {
                return (int) round((float) $matches[1] * 1000);
            }
        }

        return null;
    }

    /**
     * Belt and braces: the provider does not echo the key, but if one ever did it would
     * not travel any further than here.
     */
    private function redact(string $text): string
    {
        return $this->apiKey ? str_replace($this->apiKey, '[redacted]', $text) : $text;
    }
}
