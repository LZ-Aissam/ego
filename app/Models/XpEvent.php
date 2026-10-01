<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XpEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['source', 'source_id', 'amount', 'quantity', 'created_at'];

    protected function casts(): array
    {
        return ['source_id' => 'integer', 'amount' => 'integer', 'quantity' => 'integer', 'created_at' => 'datetime'];
    }
}
