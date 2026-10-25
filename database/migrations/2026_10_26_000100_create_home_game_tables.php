<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Jeu maison : défis choisis par le joueur et essais du jour, à part du programme. */
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->json('home_games')->nullable());

        Schema::create('home_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game', 20);
            $table->unsignedSmallInteger('value');
            $table->date('played_on');
            $table->boolean('beat_previous')->default(false);
            $table->boolean('new_record')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'game', 'played_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_attempts');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('home_games'));
    }
};
