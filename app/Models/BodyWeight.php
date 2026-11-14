<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BodyWeight extends Model
{
    protected $fillable = ['user_id', 'date', 'kg'];

    protected function casts(): array
    {
        return ['date' => 'date', 'kg' => 'float'];
    }
}
