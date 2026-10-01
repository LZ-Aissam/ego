<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_maxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained();
            $table->decimal('e1rm_kg', 6, 1);
            $table->string('source', 12)->default('calibration');
            $table->timestamp('measured_at');
            $table->timestamps();
            $table->unique(['user_id', 'exercise_id']);
        });

        Schema::create('working_loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained();
            $table->decimal('target_kg', 6, 2);
            $table->unsignedTinyInteger('fail_streak')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'exercise_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('calibrated_at')->nullable()->after('paused_until');
            $table->decimal('power_factor', 4, 2)->nullable()->after('calibrated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['calibrated_at', 'power_factor']));
        Schema::dropIfExists('working_loads');
        Schema::dropIfExists('exercise_maxes');
    }
};
