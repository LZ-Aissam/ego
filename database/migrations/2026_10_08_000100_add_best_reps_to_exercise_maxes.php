<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Exercices au poids du corps : on retient le maximum de répétitions, plus parlant qu'un 1RM. */
    public function up(): void
    {
        Schema::table('exercise_maxes', fn (Blueprint $table) => $table->unsignedSmallInteger('best_reps')->nullable()->after('e1rm_kg'));
    }

    public function down(): void
    {
        Schema::table('exercise_maxes', fn (Blueprint $table) => $table->dropColumn('best_reps'));
    }
};
