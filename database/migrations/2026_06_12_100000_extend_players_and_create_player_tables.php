<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('nickname');
            $table->string('city')->nullable()->after('avatar');
            $table->text('description')->nullable()->after('city');
            $table->boolean('is_premium')->default(false)->after('description');
            $table->timestamp('premium_until')->nullable()->after('is_premium');
        });

        Schema::create('player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('experience')->default(0);
            $table->unsignedInteger('skill_points_unspent')->default(4);
            $table->unsignedInteger('attack_skill_points')->default(0);
            $table->unsignedInteger('defense_skill_points')->default(0);
            $table->unsignedInteger('energy_skill_points')->default(0);
            $table->unsignedSmallInteger('health')->default(100);
            $table->timestamps();
        });

        Schema::create('player_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('energy')->default(40);
            $table->unsignedBigInteger('money')->default(0);
            $table->timestamp('regenerated_at')->nullable();
            $table->timestamps();

            $table->unique('player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_resources');
        Schema::dropIfExists('player_stats');
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'city', 'description', 'is_premium', 'premium_until']);
        });
    }
};
