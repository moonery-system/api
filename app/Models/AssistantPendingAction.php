<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property int $delivery_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $expires_at
 */
class AssistantPendingAction extends Model
{
    public const ACTION_CANCEL_DELIVERY = 'cancel_delivery';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $table = 'assistant_pending_actions';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'delivery_id',
        'message_id',
        'action',
        'status',
        'expires_at',
        'resolved_at',
        'error',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
