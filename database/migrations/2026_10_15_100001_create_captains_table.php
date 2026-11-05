<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['player_id', 'dismissed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captains');
    }
};
