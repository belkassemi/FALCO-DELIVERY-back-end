<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = ['phone_number', 'message_type', 'provider_response', 'status'];
}
