<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $table = 'conversations';

    public const ASSISTANT_ACTIVE = 'active';
    public const ASSISTANT_HANDED_OFF = 'handed_off';

    protected $fillable = [
        'user_id',
    ];

    protected $casts = [
        'handed_off_at' => 'datetime',
    ];

    /**
     * The person being attended. The support side is not stored: any agent replies.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest();
    }
}
