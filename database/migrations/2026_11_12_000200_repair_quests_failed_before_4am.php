<?php

use App\Game\GameDay;
use App\Game\QuestScheduler;
use App\Models\User;
use App\Support\SystemNotifier;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Journée de jeu jusqu'à 4 h du matin : les quêtes ratées dont la séance s'est finie juste après minuit
     * sont réévaluées avec les nouvelles bornes, accomplies si les séries suffisent, et le joueur en est averti.
     */
    public function up(): void
    {
        $scheduler = app(QuestScheduler::class);
        $notifier = app(SystemNotifier::class);
        $today = GameDay::today();

        User::query()->whereHas('quests', fn ($q) => $q->where('status', 'failed'))
            ->each(function (User $user) use ($scheduler, $notifier, $today) {
                foreach ($scheduler->repairFailedBeforeCutoff($user, $today) as $note) {
                    $notifier->push($user, $note);
                }
            });
    }

    public function down(): void {}
};
