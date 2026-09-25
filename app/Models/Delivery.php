<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    use HasFactory;

    protected $table = 'deliveries';

    protected $fillable = [
        'tracking_code',
        'creator_id',
        'delivery_man_id',
        'client_id',
        'client_address_id',
        'delivery_status_id',
        'scheduled_to',
        'delivered_at',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItems::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(ClientAddress::class, 'client_address_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function deliveryman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_man_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(DeliveryStatus::class, 'delivery_status_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(DeliveryStatusHistory::class)->oldest();
    }
}
