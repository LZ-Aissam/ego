<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Crew extends Model
{
    public const COLORS = ['ice' => '#5ad8ff', 'cyan' => '#22d3ee', 'violet' => '#a78bfa', 'gold' => '#ffb547', 'red' => '#ff4d6d', 'green' => '#34d399'];

    public const COLOR_NAMES = ['ice' => 'Glace', 'cyan' => 'Cyan', 'violet' => 'Violet', 'gold' => 'Or', 'red' => 'Rouge', 'green' => 'Vert'];

    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $fillable = ['name', 'color', 'code', 'owner_id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('joined_at');
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    public function hex(): string
    {
        return self::COLORS[$this->color] ?? self::COLORS['ice'];
    }

    public static function generateCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[\s\-]+/', '', $code));
    }
}
