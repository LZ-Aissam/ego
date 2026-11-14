<?php

namespace App\Http\Middleware;

use App\Game\GameDay;
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
            foreach ($this->quests->sync($user, GameDay::today()) as $notification) {
                $this->notifier->push($user, $notification);
            }
            $this->challenges->tick($user);
            if ($report = app(\App\Game\WeeklyReport::class)->due($user, GameDay::today())) {
                $this->notifier->push($user, $report);
            }
        }

        return $next($request);
    }
}
