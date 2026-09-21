<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'delivery_status_history';

    /**
     * History rows are immutable: they are written once and never updated.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'delivery_id',
        'delivery_status_id',
        'user_id',
    ];

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function status()
    {
        return $this->belongsTo(DeliveryStatus::class, 'delivery_status_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
