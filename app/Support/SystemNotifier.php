<?php

namespace App\Support;

use App\Models\SystemNotification;
use App\Models\User;

final class SystemNotifier
{
    public function push(User $user, array $notification): void
    {
        SystemNotification::create(['user_id' => $user->id, 'type' => $notification['type'], 'payload' => $notification]);
    }

    /** @return list<array> */
    public function pullUnseen(User $user): array
    {
        $rows = SystemNotification::where('user_id', $user->id)->whereNull('seen_at')->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return [];
        }

        SystemNotification::whereIn('id', $rows->pluck('id'))->update(['seen_at' => now()]);

        return $rows->pluck('payload')->all();
    }
}
