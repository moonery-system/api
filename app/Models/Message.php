<?php

namespace App\Models;

use App\Assistant\AssistantBot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Message extends Model
{
    use HasFactory;

    protected $table = 'messages';

    /**
     * Messages are immutable once sent.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'delivery_id',
        'body',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * Serialized with every message, so a screen can tell the assistant from a human of
     * support without knowing the bot's name or e-mail.
     *
     * @var array<int, string>
     */
    protected $appends = ['is_assistant'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    /**
     * The confirmation this message is asking for, when it is the assistant asking
     * "do you confirm?".
     */
    public function pendingAction(): HasOne
    {
        return $this->hasOne(AssistantPendingAction::class);
    }

    public function getIsAssistantAttribute(): bool
    {
        $botId = app(AssistantBot::class)->id();

        return $botId !== null && (int) $this->sender_id === $botId;
    }
}
