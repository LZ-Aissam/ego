<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_exercises', fn (Blueprint $table) => $table->string('intent', 10)->nullable()->after('rest_seconds'));
    }

    public function down(): void
    {
        Schema::table('program_exercises', fn (Blueprint $table) => $table->dropColumn('intent'));
    }
};
