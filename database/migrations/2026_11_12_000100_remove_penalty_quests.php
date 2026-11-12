<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Fin du système de pénalité : les quêtes de pénalité et le titre Revenant disparaissent (l'XP déjà gagnée reste). */
    public function up(): void
    {
        DB::table('quests')->where('type', 'penalty')->delete();
        DB::table('user_titles')->where('title_key', 'revenant')->delete();
        DB::table('users')->where('equipped_title', 'revenant')->update(['equipped_title' => null]);
    }

    public function down(): void
    {
        // Les données supprimées ne sont pas reconstituables.
    }
};
