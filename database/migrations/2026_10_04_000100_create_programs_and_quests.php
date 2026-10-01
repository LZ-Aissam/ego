<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('split', 12);
            $table->json('params');
            $table->unsignedBigInteger('copied_from_id')->nullable();
            $table->timestamps();
        });

        Schema::create('program_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('name', 30);
            $table->string('type', 12);
            $table->timestamps();
        });

        Schema::create('program_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_day_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->foreignId('exercise_id')->constrained();
            $table->string('slot', 20);
            $table->unsignedTinyInteger('sets');
            $table->unsignedTinyInteger('reps_min');
            $table->unsignedTinyInteger('reps_max');
            $table->unsignedSmallInteger('rest_seconds');
            $table->timestamps();
        });

        Schema::create('quests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 10);
            $table->foreignId('program_day_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 40);
            $table->json('objectives');
            $table->string('status', 10)->default('pending');
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date', 'type']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('training_weekdays')->nullable()->after('equipment');
            $table->unsignedInteger('streak_days')->default(0)->after('equipped_title');
            $table->unsignedInteger('best_streak')->default(0)->after('streak_days');
            $table->date('paused_at')->nullable()->after('best_streak');
            $table->date('paused_until')->nullable()->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['training_weekdays', 'streak_days', 'best_streak', 'paused_at', 'paused_until']));
        Schema::dropIfExists('quests');
        Schema::dropIfExists('program_exercises');
        Schema::dropIfExists('program_days');
        Schema::dropIfExists('programs');
    }
};
