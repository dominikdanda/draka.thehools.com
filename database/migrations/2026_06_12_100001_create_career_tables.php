<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('artwork_path')->nullable();
            $table->string('type')->default('classic');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('completion_rewards')->nullable();
            $table->timestamps();
        });

        Schema::create('career_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->unique();
            $table->string('name');
            $table->unsignedInteger('value')->default(0);
            $table->timestamps();
        });

        Schema::create('career_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->text('introduction')->nullable();
            $table->string('artwork_path')->nullable();
            $table->unsignedInteger('required_player_level')->default(0);
            $table->foreignId('required_chapter_id')->nullable()->constrained('career_chapters')->nullOnDelete();
            $table->json('featured_item_legacy_ids')->nullable();
            $table->json('mastery_rewards')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'number']);
        });

        Schema::create('career_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained('career_chapters')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->unsignedInteger('global_number');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->unsignedInteger('energy_cost');
            $table->unsignedInteger('money_reward_min');
            $table->unsignedInteger('money_reward_max');
            $table->unsignedInteger('experience_reward');
            $table->foreignId('reward_item_id')->nullable()->constrained('career_items')->nullOnDelete();
            $table->foreignId('reward_item_id_2')->nullable()->constrained('career_items')->nullOnDelete();
            $table->foreignId('required_item_id')->nullable()->constrained('career_items')->nullOnDelete();
            $table->foreignId('required_item_id_2')->nullable()->constrained('career_items')->nullOnDelete();
            $table->timestamps();

            $table->unique(['chapter_id', 'number']);
            $table->unique('global_number');
        });

        Schema::create('player_career_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('career_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['player_id', 'career_item_id']);
        });

        Schema::create('player_chapter_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained('career_chapters')->cascadeOnDelete();
            $table->unsignedTinyInteger('mastery_level')->default(0);
            $table->timestamps();

            $table->unique(['player_id', 'chapter_id']);
        });

        Schema::create('player_stage_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('career_stages')->cascadeOnDelete();
            $table->unsignedSmallInteger('mastery_progress')->default(0);
            $table->unsignedInteger('completions')->default(0);
            $table->timestamps();

            $table->unique(['player_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_stage_progress');
        Schema::dropIfExists('player_chapter_progress');
        Schema::dropIfExists('player_career_items');
        Schema::dropIfExists('career_stages');
        Schema::dropIfExists('career_chapters');
        Schema::dropIfExists('career_items');
        Schema::dropIfExists('campaigns');
    }
};
