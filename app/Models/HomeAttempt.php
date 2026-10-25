<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Essai du jeu maison (max de répétitions ou de secondes), sans lien avec le programme. */
class HomeAttempt extends Model
{
    protected $fillable = ['user_id', 'game', 'value', 'played_on', 'beat_previous', 'new_record'];

    protected function casts(): array
    {
        return ['played_on' => 'date', 'value' => 'integer', 'beat_previous' => 'boolean', 'new_record' => 'boolean'];
    }
}
