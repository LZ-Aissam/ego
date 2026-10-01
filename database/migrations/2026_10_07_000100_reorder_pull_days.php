<?php

use App\Services\ProgramGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Journées Pull existantes : charnière de hanche d'abord (même règle que le générateur). Seul l'ordre change. */
    public function up(): void
    {
        foreach (DB::table('program_days')->where('type', 'pull')->pluck('id') as $dayId) {
            $lines = DB::table('program_exercises')->where('program_day_id', $dayId)->orderBy('position')->get(['id', 'slot'])
                ->map(fn ($line) => (array) $line)->all();

            foreach (ProgramGenerator::inExecutionOrder('pull', $lines) as $i => $line) {
                DB::table('program_exercises')->where('id', $line['id'])->update(['position' => $i + 1]);
            }
        }
    }

    public function down(): void {}
};
