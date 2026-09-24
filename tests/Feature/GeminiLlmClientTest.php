<?php

namespace Tests\Feature;

use App\Assistant\Llm\Exceptions\LlmAuthException;
use App\Assistant\Llm\Exceptions\LlmProviderException;
use App\Assistant\Llm\Exceptions\LlmRateLimitException;
use App\Assistant\Llm\Exceptions\LlmTimeoutException;
use App\Assistant\Llm\GeminiLlmClient;
use App\Assistant\Llm\LlmMessage;
use App\Assistant\Llm\LlmRequest;
use App\Assistant\Llm\ToolDefinition;
use App\Assistant\Llm\ToolResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * No network and no key: Http::fake answers every request, and the "key" below is made up
 * on purpose in a format that is not Google's, to prove nothing depends on how it looks.
 */
class GeminiLlmClientTest extends TestCase
{
    private const KEY = 'gm-test-key-0123456789';

    private function gemini(?string $key = self::KEY, ?string $model = 'gemini-test-model'): GeminiLlmClient
    {
        return new GeminiLlmClient(
            apiKey: $key,
            model: $model,
            baseUrl: 'https://generativelanguage.googleapis.com/v1beta',
            timeoutSeconds: 20,
        );
    }

    private function llmRequest(array $messages = null): LlmRequest
    {
        return new LlmRequest(
            systemPrompt: 'be brief',
            messages: $messages ?? [LlmMessage::user('onde esta meu pacote?')],
            tools: [new ToolDefinition('get_delivery', 'Looks up a delivery', [
                'type' => 'object',
                'properties' => ['tracking_code' => ['type' => 'string']],
            ])],
            maxTokens: 256,
        );
    }

    private function fakeReply(array $body, int $status = 200, array $headers = []): void
    {
        // Http::fake() stacks stubs and the first one that matches wins, so a test that
        // fakes twice would keep hearing the first answer. Start from a fresh factory.
        $this->app->forgetInstance(\Illuminate\Http\Client\Factory::class);
        Http::clearResolvedInstances();

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($body, $status, $headers)]);
    }

    private function functionCallReply(): array
    {
        return [
            'candidates' => [[
                'content' => ['role' => 'model', 'parts' => [[
                    'functionCall' => ['name' => 'get_delivery', 'args' => ['tracking_code' => 'MNY-2026-SEED01']],
                    'thoughtSignature' => 'opaque-signature-abc',
                ]]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 12, 'thoughtsTokenCount' => 8, 'totalTokenCount' => 120],
        ];
    }

    public function test_a_function_call_becomes_a_tool_call(): void
    {
        $this->fakeReply($this->functionCallReply());

        $response = $this->gemini()->generate($this->llmRequest());

        $this->assertTrue($response->hasToolCalls());
        $this->assertSame('get_delivery', $response->toolCalls[0]->name);
        $this->assertSame(['tracking_code' => 'MNY-2026-SEED01'], $response->toolCalls[0]->arguments);
        $this->assertNull($response->text);
    }

    public function test_usage_counts_the_reasoning_tokens_as_output(): void
    {
        $this->fakeReply($this->functionCallReply());

        $usage = $this->gemini()->generate($this->llmRequest())->usage;

        $this->assertSame(100, $usage->inputTokens);
        $this->assertSame(20, $usage->outputTokens);
    }

    public function test_the_tool_result_goes_back_as_a_function_response_and_the_raw_parts_are_echoed(): void
    {
        $this->fakeReply($this->functionCallReply());
        $first = $this->gemini()->generate($this->llmRequest());

        $this->fakeReply(['candidates' => [['content' => ['parts' => [['text' => 'Esta em transito.']]], 'finishReason' => 'STOP']]]);

        $followUp = $this->llmRequest([
            LlmMessage::user('onde esta meu pacote?'),
            LlmMessage::fromResponse($first),
            LlmMessage::toolResults([
                new ToolResult($first->toolCalls[0]->id, 'get_delivery', ['status' => 'in_transit']),
            ]),
        ]);

        $answer = $this->gemini()->generate($followUp);

        $this->assertSame('Esta em transito.', $answer->text);

        Http::assertSent(function (Request $request) {
            $contents = $request['contents'];

            if (count($contents) !== 3) return false;

            // The model turn is exactly what the provider sent, thought signature included.
            $echoed = $contents[1]['parts'][0]['thoughtSignature'] ?? null;

            $response = $contents[2]['parts'][0]['functionResponse'] ?? [];

            return $contents[1]['role'] === 'model'
                && $echoed === 'opaque-signature-abc'
                && $contents[2]['role'] === 'user'
                && $response['name'] === 'get_delivery'
                && $response['response']['result'] === ['status' => 'in_transit'];
        });
    }

    public function test_the_request_carries_the_tools_the_system_prompt_and_the_token_limit(): void
    {
        $this->fakeReply(['candidates' => [['content' => ['parts' => [['text' => 'oi']]]]]]);

        $this->gemini()->generate($this->llmRequest());

        Http::assertSent(function (Request $request) {
            return $request['systemInstruction']['parts'][0]['text'] === 'be brief'
                && $request['generationConfig']['maxOutputTokens'] === 256
                && $request['tools'][0]['functionDeclarations'][0]['name'] === 'get_delivery'
                && str_contains($request->url(), '/models/gemini-test-model:generateContent');
        });
    }

    public function test_the_key_goes_in_the_header_and_never_in_the_url(): void
    {
        $this->fakeReply(['candidates' => [['content' => ['parts' => [['text' => 'oi']]]]]]);

        $this->gemini()->generate($this->llmRequest());

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('x-goog-api-key', self::KEY)
                && !str_contains($request->url(), self::KEY)
                && !str_contains($request->url(), 'key=');
        });
    }

    public function test_the_key_never_reaches_a_log_nor_an_exception(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            $logged[] = $event->message . json_encode($event->context);
        });

        // Even a provider that echoed the key in its error must not get it past the client.
        $this->fakeReply(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'API key ' . self::KEY . ' is not valid']], 400);

        try {
            $this->gemini()->generate($this->llmRequest());
            $this->fail('a 400 must raise');
        } catch (LlmAuthException $e) {
            $this->assertStringNotContainsString(self::KEY, $e->getMessage());
            $this->assertStringContainsString('HTTP 400', $e->getMessage());
            $this->assertStringNotContainsString(self::KEY, $e->getTraceAsString());
        }

        $this->assertStringNotContainsString(self::KEY, implode('', $logged));
    }

    public function test_400_401_and_403_are_authentication_errors(): void
    {
        foreach ([400, 401, 403] as $status) {
            $this->fakeReply(['error' => ['message' => 'nope', 'status' => 'PERMISSION_DENIED']], $status);

            try {
                $this->gemini()->generate($this->llmRequest());
                $this->fail("{$status} must raise");
            } catch (LlmAuthException $e) {
                $this->assertStringContainsString("HTTP {$status}", $e->getMessage());
            }
        }
    }

    public function test_a_missing_key_is_a_clear_error_and_no_request_is_made(): void
    {
        Http::fake();

        $this->expectException(LlmAuthException::class);
        $this->expectExceptionMessage('GEMINI_API_KEY');

        try {
            $this->gemini(key: null)->generate($this->llmRequest());
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_a_429_carries_the_delay_the_provider_suggests(): void
    {
        $this->fakeReply([
            'error' => [
                'code' => 429,
                'status' => 'RESOURCE_EXHAUSTED',
                'message' => 'Quota exceeded',
                'details' => [['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => '33s']],
            ],
        ], 429);

        try {
            $this->gemini()->generate($this->llmRequest());
            $this->fail('a 429 must raise');
        } catch (LlmRateLimitException $e) {
            $this->assertSame(33000, $e->retryAfterMs);
        }
    }

    public function test_the_retry_after_header_is_also_understood(): void
    {
        $this->fakeReply(['error' => ['message' => 'slow down']], 429, ['Retry-After' => '12']);

        try {
            $this->gemini()->generate($this->llmRequest());
            $this->fail('a 429 must raise');
        } catch (LlmRateLimitException $e) {
            $this->assertSame(12000, $e->retryAfterMs);
        }
    }

    public function test_a_429_without_a_suggestion_has_no_delay(): void
    {
        $this->fakeReply(['error' => ['message' => 'slow down']], 429);

        try {
            $this->gemini()->generate($this->llmRequest());
            $this->fail('a 429 must raise');
        } catch (LlmRateLimitException $e) {
            $this->assertNull($e->retryAfterMs);
        }
    }

    public function test_5xx_is_retryable_and_other_errors_are_not(): void
    {
        $this->fakeReply(['error' => ['message' => 'overloaded']], 503);

        try {
            $this->gemini()->generate($this->llmRequest());
            $this->fail('a 503 must raise');
        } catch (LlmProviderException $e) {
            $this->assertTrue($e->retryable);
        }

        $this->fakeReply(['error' => ['message' => 'no such model']], 404);

        try {
            $this->gemini()->generate($this->llmRequest());
            $this->fail('a 404 must raise');
        } catch (LlmProviderException $e) {
            $this->assertFalse($e->retryable);
        }
    }

    public function test_a_connection_failure_is_a_timeout(): void
    {
        Http::fake(fn() => throw new ConnectionException('cURL error 28: timed out'));

        $this->expectException(LlmTimeoutException::class);

        $this->gemini()->generate($this->llmRequest());
    }

    public function test_a_reply_without_text_or_tool_calls_comes_back_empty(): void
    {
        $this->fakeReply(['candidates' => [['finishReason' => 'SAFETY']]]);

        $response = $this->gemini()->generate($this->llmRequest());

        $this->assertNull($response->text);
        $this->assertFalse($response->hasToolCalls());
        $this->assertSame('SAFETY', $response->finishReason);
    }
}
