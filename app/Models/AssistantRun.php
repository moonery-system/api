<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $message_id
 * @property int $conversation_id
 * @property int $user_id
 * @property string $status
 * @property int $iterations
 * @property int $input_tokens
 * @property int $output_tokens
 */
class AssistantRun extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FALLBACK = 'fallback';

    protected $table = 'assistant_runs';

    protected $fillable = [
        'message_id',
        'conversation_id',
        'user_id',
        'provider',
        'model',
        'status',
        'iterations',
        'input_tokens',
        'output_tokens',
        'latency_ms',
        'error',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
