<?php

namespace App\Http\Middleware;

use App\Game\QuestScheduler;
use App\Support\SystemNotifier;
use Closure;
use Illuminate\Http\Request;

class SyncQuests
{
    public function __construct(private QuestScheduler $quests, private SystemNotifier $notifier) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $request->isMethod('GET') && ! $request->expectsJson()) {
            foreach ($this->quests->sync($user, now()) as $notification) {
                $this->notifier->push($user, $notification);
            }
        }

        return $next($request);
    }
}
