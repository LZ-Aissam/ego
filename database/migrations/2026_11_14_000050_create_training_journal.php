<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            // Forme du jour annoncée avant la séance : low (fatigué), normal, high (au top).
            $table->string('readiness', 8)->nullable()->after('skipped_exercise_ids');
        });

        // Carnet par exercice : une note libre et/ou une douleur signalée (zone), relue à la séance suivante.
        Schema::create('exercise_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->string('text', 280)->nullable();
            $table->string('pain_zone', 16)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'exercise_id']);
        });

        // Pesées : une par jour au plus, la dernière fait foi pour le lest et la force relative.
        Schema::create('body_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('kg', 5, 1);
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Lundi de la dernière semaine dont le récap a été envoyé.
            $table->date('weekly_report_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('weekly_report_on'));
        Schema::dropIfExists('body_weights');
        Schema::dropIfExists('exercise_notes');
        Schema::table('workouts', fn (Blueprint $table) => $table->dropColumn('readiness'));
    }
};
