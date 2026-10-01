<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('xp_total')->default(0)->after('is_admin');
            $table->string('equipped_title', 30)->nullable()->after('xp_total');
        });

        Schema::create('xp_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->unsignedBigInteger('source_id');
            $table->unsignedInteger('amount');
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->timestamp('created_at')->nullable()->index();
            $table->unique(['user_id', 'source', 'source_id']);
        });

        Schema::create('user_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title_key', 30);
            $table->timestamp('unlocked_at');
            $table->unique(['user_id', 'title_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_titles');
        Schema::dropIfExists('xp_events');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['xp_total', 'equipped_title']));
    }
};
