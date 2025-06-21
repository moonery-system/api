<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryItems extends Model
{
    use HasFactory;

    protected $table = 'delivery_items';

    protected $fillable = [
        'delivery_id',
        'name',
        'description',
        'quantity',
        'weight'
    ];

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }
}
