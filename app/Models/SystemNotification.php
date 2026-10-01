<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    protected $fillable = ['user_id', 'type', 'payload', 'seen_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'seen_at' => 'datetime'];
    }
}
