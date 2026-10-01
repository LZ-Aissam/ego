<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id')->unique();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->index();
            $table->decimal('bodyweight_kg', 5, 1)->nullable();
            $table->timestamps();
        });

        Schema::create('workout_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained();
            $table->unsignedSmallInteger('position');
            $table->decimal('weight_kg', 6, 2);
            $table->unsignedSmallInteger('reps');
            $table->dateTime('done_at');
            $table->timestamps();
        });

        Schema::create('personal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained();
            $table->decimal('max_weight_kg', 6, 2);
            $table->unsignedSmallInteger('max_weight_reps');
            $table->decimal('best_e1rm_kg', 6, 1);
            $table->dateTime('achieved_at');
            $table->timestamps();
            $table->unique(['user_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_records');
        Schema::dropIfExists('workout_sets');
        Schema::dropIfExists('workouts');
    }
};
