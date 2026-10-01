<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** « calibration » (11 caractères) dépasse l'ancienne limite de 10. */
    public function up(): void
    {
        Schema::table('quests', fn (Blueprint $table) => $table->string('type', 20)->change());
    }

    public function down(): void
    {
        Schema::table('quests', fn (Blueprint $table) => $table->string('type', 10)->change());
    }
};
