<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('ego:backfill-xp');
    }

    public function down(): void
    {
        // Données dérivées : rien à annuler (la migration des tables les supprime).
    }
};
