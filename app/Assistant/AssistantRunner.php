<?php

namespace App\Assistant;

use App\Assistant\Llm\Exceptions\LlmException;
use App\Assistant\Llm\LlmClient;
use App\Assistant\Llm\LlmMessage;
use App\Assistant\Llm\LlmRequest;
use App\Assistant\Llm\ToolCall;
use App\Assistant\Llm\ToolResult;
use App\Assistant\Support\Clock;
use App\Assistant\Tools\ToolContext;
use App\Contracts\Repositories\AssistantPendingActionInterface;
use App\Contracts\Repositories\AssistantRunInterface;
use App\Contracts\Repositories\ConversationInterface;
use App\Contracts\Repositories\MessageInterface;
use App\Contracts\Repositories\UserInterface;
use App\Enums\LogEventTypeEnum;
use App\Models\AssistantPendingAction;
use App\Models\AssistantRun;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\LogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Answers one customer message: the tool loop.
 *
 * The loop is ours, not the provider's -- call the model, run the tools it asked for, hand
 * the results back -- so swapping the provider changes nothing here.
 *
 * Whatever goes wrong ends the same way: a fixed message to the customer and the
 * conversation handed over to support. The customer never sees an error, and the
 * assistant never stays silent without a human being told.
 */
class AssistantRunner
{
    // Tool calls honoured in one model turn; the rest are answered with an error.
    private const MAX_CALLS_PER_TURN = 5;

    private const MAX_REPLY_CHARS = 2000;

    public function __construct(
        private LlmClient $llm,
        private ToolRegistry $tools,
        private ConversationService $conversationService,
        private ConversationInterface $conversationRepository,
        private MessageInterface $messageRepository,
        private UserInterface $userRepository,
        private AssistantRunInterface $runRepository,
        private AssistantPendingActionInterface $pendingActions,
        private LogService $logService,
        private Clock $clock,
    ) {}

    public function handle(int $messageId): void
    {
        if (!config('assistant.enabled')) return;

        $message = $this->messageRepository->findById($messageId);

        if (!$message) return;

        $conversation = $message->conversation;
        $customer = $this->userRepository->findById($conversation->user_id);
        $bot = $this->userRepository->findByEmail((string) config('assistant.bot_email'));

        if (!$customer || !$bot) {
            Log::error('The assistant cannot run: the customer or the bot user is missing.', ['message_id' => $messageId]);

            return;
        }

        if (!$this->stillEligible($message, $conversation, $customer)) return;

        $run = $this->runRepository->claim(
            messageId: $message->id,
            conversationId: $conversation->id,
            userId: $customer->id,
            provider: $this->llm->provider(),
            model: $this->llm->model(),
            staleAfterSeconds: (int) config('assistant.run_deadline_seconds'),
        );

        // Already answered (or being answered): the same message never gets two replies.
        if (!$run) return;

        $startedAt = $this->clock->nowMs();
        $context = new ToolContext($customer, $conversation);
        $usage = ['input' => 0, 'output' => 0, 'iterations' => 0];

        try {
            $this->assertUnderUserLimit($customer, $message);

            $reply = $this->converse($message, $conversation, $context, $startedAt, $usage);

            $this->deliver($conversation, $bot, $context, $reply);

            $this->finish($run, AssistantRun::STATUS_COMPLETED, $usage, $startedAt);
        } catch (\Throwable $e) {
            $this->fallback($conversation, $bot, $customer, $context, $run, $usage, $startedAt, $e);
        }
    }

    /**
     * The state may have moved between the queueing and now (a human answered, the
     * permission was taken away): decide again with what is true now.
     */
    private function stillEligible(Message $message, Conversation $conversation, User $customer): bool
    {
        return $conversation->assistant_status === Conversation::ASSISTANT_ACTIVE
            && $message->sender_id === $conversation->user_id
            && $customer->hasPermission('assistant.use')
            && !$customer->hasPermission('chat.viewAll');
    }

    private function assertUnderUserLimit(User $customer, Message $message): void
    {
        $limit = config('assistant.user_rate_limit');

        $recent = $this->runRepository->countForUserSince(
            userId: $customer->id,
            since: Carbon::now()->subSeconds((int) $limit['window_seconds']),
            exceptMessageId: $message->id,
        );

        if ($recent >= (int) $limit['runs']) {
            throw new AssistantLimitReached('user_rate_limit');
        }
    }

    /**
     * Runs the loop and returns what to say. Throws when it cannot get there.
     *
     * @param array{input: int, output: int, iterations: int} $usage
     */
    private function converse(Message $message, Conversation $conversation, ToolContext $context, int $startedAt, array &$usage): string
    {
        $deadline = $startedAt + ((int) config('assistant.run_deadline_seconds')) * 1000;

        $request = new LlmRequest(
            systemPrompt: $this->systemPrompt(),
            messages: $this->history($message, $conversation),
            tools: $this->tools->definitions(),
            maxTokens: (int) config('assistant.max_output_tokens'),
            deadlineMs: $deadline,
        );

        $messages = $request->messages;

        for ($i = 0; $i < (int) config('assistant.max_iterations'); $i++) {
            $response = $this->llm->generate($request->withMessages($messages));

            $usage['iterations']++;
            $usage['input'] += $response->usage->inputTokens;
            $usage['output'] += $response->usage->outputTokens;

            if (!$response->hasToolCalls()) {
                return $this->finalText($response->text, $context);
            }

            $messages[] = LlmMessage::fromResponse($response);
            $messages[] = LlmMessage::toolResults($this->runTools($response->toolCalls, $context));
        }

        throw new AssistantLimitReached('iterations_exceeded');
    }

    /**
     * @param array<int, ToolCall> $calls
     * @return array<int, ToolResult>
     */
    private function runTools(array $calls, ToolContext $context): array
    {
        $results = [];

        foreach (array_values($calls) as $position => $call) {
            // Every call gets an answer (the provider needs them to match), but only the
            // first few are honoured.
            $results[] = $position < self::MAX_CALLS_PER_TURN
                ? $this->tools->execute($call, $context)
                : new ToolResult($call->id, $call->name, ['message' => 'Too many tool calls in one turn.'], isError: true);
        }

        return $results;
    }

    /**
     * What is said. When a cancellation is waiting for confirmation, the words are ours and
     * not the model's: the model must not be able to tell the customer something was
     * canceled when it was only asked.
     */
    private function finalText(?string $text, ToolContext $context): string
    {
        if ($context->handoffRequested()) {
            $text = trim((string) $text);

            return $text !== '' ? $text : (string) config('assistant.messages.handoff');
        }

        if ($context->pendingActionId !== null) {
            return str_replace(':tracking_code', (string) $context->pendingTrackingCode, (string) config('assistant.messages.confirm_cancel'));
        }

        $text = trim((string) $text);

        if ($text === '') throw new AssistantLimitReached('empty_response');

        return $text;
    }

    /**
     * Posts the answer, and applies what the tools asked for.
     */
    private function deliver(Conversation $conversation, User $bot, ToolContext $context, string $reply): void
    {
        $handoff = $context->handoffRequested();

        $message = $this->conversationService->sendAsAssistant(
            conversation: $conversation,
            bot: $bot,
            body: mb_substr($reply, 0, self::MAX_REPLY_CHARS),
            deliveryId: $handoff ? null : $context->pendingDeliveryId,
        );

        if ($context->pendingActionId !== null) {
            if ($handoff) {
                // Handing over and asking for a confirmation contradict each other.
                $this->pendingActions->resolve($context->pendingActionId, AssistantPendingAction::STATUS_SUPERSEDED);
            } else {
                $this->pendingActions->attachMessage($context->pendingActionId, $message->id);
            }
        }

        $this->logService->record(eventType: LogEventTypeEnum::ASSISTANT_REPLIED, context: [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'awaiting_confirmation' => $context->pendingActionId !== null && !$handoff,
        ], userId: $bot->id);

        if ($handoff) {
            $this->handOff($conversation, $bot, 'assistant_request', $context->handoffNote);
        }
    }

    /**
     * @param array{input: int, output: int, iterations: int} $usage
     */
    private function fallback(Conversation $conversation, User $bot, User $customer, ToolContext $context, AssistantRun $run, array $usage, int $startedAt, \Throwable $error): void
    {
        $reason = $this->reasonFor($error);

        Log::warning('The assistant fell back to support', [
            'message_id' => $run->message_id,
            'reason' => $reason,
            'error' => $error::class,
        ]);

        // A confirmation created earlier in this run will never be asked: drop it.
        if ($context->pendingActionId !== null) {
            $this->pendingActions->resolve($context->pendingActionId, AssistantPendingAction::STATUS_SUPERSEDED);
        }

        $this->conversationService->sendAsAssistant(
            conversation: $conversation,
            bot: $bot,
            body: (string) config('assistant.messages.fallback'),
        );

        $this->handOff($conversation, $bot, $reason, $error instanceof LlmException || $error instanceof AssistantLimitReached ? $error->getMessage() : null);

        $this->finish($run, AssistantRun::STATUS_FALLBACK, $usage, $startedAt, error: mb_substr($error::class . ': ' . $error->getMessage(), 0, 500));
    }

    private function reasonFor(\Throwable $error): string
    {
        return match (true) {
            $error instanceof AssistantLimitReached => $error->reason,
            $error instanceof Llm\Exceptions\LlmDailyCapReachedException,
            $error instanceof Llm\Exceptions\LlmDeadlineExceededException => 'limit_exceeded',
            default => 'provider_failure',
        };
    }

    private function handOff(Conversation $conversation, User $bot, string $reason, ?string $note): void
    {
        // Conditional: if a human already took over, there is nothing to flip or to log.
        if ($this->conversationRepository->markHandedOff($conversation->id, $reason) === 0) return;

        $this->logService->record(eventType: LogEventTypeEnum::ASSISTANT_HANDOFF, context: [
            'conversation_id' => $conversation->id,
            'reason' => $reason,
            'note' => $note,
        ], userId: $bot->id);
    }

    /**
     * @param array{input: int, output: int, iterations: int} $usage
     */
    private function finish(AssistantRun $run, string $status, array $usage, int $startedAt, ?string $error = null): void
    {
        $this->runRepository->finish($run, [
            'status' => $status,
            'iterations' => $usage['iterations'],
            'input_tokens' => $usage['input'],
            'output_tokens' => $usage['output'],
            'latency_ms' => max(0, $this->clock->nowMs() - $startedAt),
            'error' => $error,
        ]);
    }

    /**
     * The conversation up to the message being answered, as the model sees it: the
     * customer is "user", the assistant is "assistant", and a human of support is left out
     * (a human answering silences the assistant, so it would not be here to read it).
     * Consecutive turns of the same side are merged: the providers want them to alternate.
     *
     * @return array<int, LlmMessage>
     */
    private function history(Message $message, Conversation $conversation): array
    {
        $max = (int) config('assistant.max_input_chars');
        $botId = $this->userRepository->findByEmail((string) config('assistant.bot_email'))?->id;

        $turns = [];

        foreach ($this->messageRepository->recentForConversation($conversation->id, (int) config('assistant.history_messages')) as $past) {
            if ($past->id > $message->id) continue;

            $role = match (true) {
                $past->sender_id === $conversation->user_id => LlmMessage::ROLE_USER,
                $past->sender_id === $botId => LlmMessage::ROLE_ASSISTANT,
                default => null,
            };

            if ($role === null) continue;

            $text = mb_substr((string) $past->body, 0, $max);

            if ($turns && $turns[array_key_last($turns)][0] === $role) {
                $turns[array_key_last($turns)][1] .= "\n" . $text;
            } else {
                $turns[] = [$role, $text];
            }
        }

        // The first turn has to be the customer's.
        while ($turns && $turns[0][0] !== LlmMessage::ROLE_USER) array_shift($turns);

        return array_map(
            fn(array $turn) => $turn[0] === LlmMessage::ROLE_USER ? LlmMessage::user($turn[1]) : LlmMessage::assistant($turn[1]),
            $turns
        );
    }

    private function systemPrompt(): string
    {
        return trim((string) file_get_contents(resource_path('prompts/assistant.md')))
            . "\n\nToday is " . Carbon::now()->toDateString() . '.';
    }
}
