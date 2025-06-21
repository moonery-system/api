<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $table = 'deliveries';

    protected $fillable = [
        'tracking_code',
        'creator_id',
        'delivery_man_id',
        'client_id',
        'delivery_status_id',
        'scheduled_to',
        'delivered_at',
    ];

    public function items()
    {
        return $this->hasMany(DeliveryItems::class);
    }
}
