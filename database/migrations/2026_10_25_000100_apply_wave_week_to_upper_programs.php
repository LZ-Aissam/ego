<?php

use App\Models\Program;
use App\Services\ProgramRefresher;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Force pure sans jambes sur 5 jours : la semaine passe à la vague 5/3/1, sur le programme actif lui-même
     * (même cycle, mêmes maxis et charges, pas de nouveau calibrage).
     */
    public function up(): void
    {
        Program::query()->whereIn('id', \App\Models\User::query()->whereNotNull('active_program_id')->pluck('active_program_id'))
            ->get()
            ->filter(fn (Program $p) => ($p->params['goal'] ?? null) === 'pure' && ($p->params['upper_only'] ?? false) && count($p->params['weekdays'] ?? []) === 5)
            ->each(fn (Program $p) => app(ProgramRefresher::class)->refresh($p));
    }

    public function down(): void {}
};
