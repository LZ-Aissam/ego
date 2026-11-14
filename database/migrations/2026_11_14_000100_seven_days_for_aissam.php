<?php

use App\Models\Program;
use App\Models\User;
use App\Services\ProgramRefresher;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Aïssam s'entraîne désormais 7 jours sur 7 : son programme Force pure sans jambes passe de 5 à 7 jours,
     * sur place (même programme, même cycle, mêmes maxis, charges et lest, pas de nouveau calibrage).
     */
    public function up(): void
    {
        User::query()->whereIn('first_name', ['Aïssam', 'Aissam'])->whereNotNull('active_program_id')->get()
            ->each(function (User $user) {
                $program = Program::find($user->active_program_id);
                if ($program && ($program->params['goal'] ?? null) === 'pure' && ($program->params['upper_only'] ?? false)
                    && count($program->params['weekdays'] ?? []) < 7) {
                    app(ProgramRefresher::class)->extend($program, [1, 2, 3, 4, 5, 6, 7]);
                }
            });
    }

    public function down(): void {}
};
