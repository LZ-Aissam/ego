<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            // Exercices passés faute de temps pendant la séance : à reprendre, remontés à la séance suivante.
            $table->json('skipped_exercise_ids')->nullable()->after('bodyweight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropColumn('skipped_exercise_ids');
        });
    }
};
