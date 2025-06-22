<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'title',
        'description',
        'notification_type_id'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_notifications');
    }
}
