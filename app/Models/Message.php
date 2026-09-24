<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function delivery()
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
}
