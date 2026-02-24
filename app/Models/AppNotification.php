<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    // Use AppNotification to avoid conflict with Laravel's built-in Notification
    protected $table = 'notifications';

    protected $fillable = ['user_id', 'title', 'body', 'type', 'data', 'read_at'];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
