<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientAddress extends Model
{
    use HasFactory;

    protected $table = 'client_addresses';

    protected $fillable = [
        'user_id',
        'address_line',
        'neighborhood',
        'city',
        'state',
        'zip_code',
        'complement'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
