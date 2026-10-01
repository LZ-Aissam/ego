<?php

namespace App\Http\Middleware;

use App\Game\QuestScheduler;
use App\Support\SystemNotifier;
use Closure;
use Illuminate\Http\Request;

class SyncQuests
{
    public function __construct(private QuestScheduler $quests, private SystemNotifier $notifier, private \App\Crew\ChallengeClock $challenges) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $request->isMethod('GET') && ! $request->expectsJson()) {
            foreach ($this->quests->sync($user, now()) as $notification) {
                $this->notifier->push($user, $notification);
            }
            $this->challenges->tick($user);
        }

        return $next($request);
    }
}
