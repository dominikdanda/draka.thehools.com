<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicates = DB::table('player_stats')
            ->select('player_id')
            ->groupBy('player_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException(
                'Duplicate player_stats.player_id values found. Review and resolve them manually before retrying this migration. No player data has been changed.'
            );
        }

        Schema::table('player_stats', function (Blueprint $table) {
            $table->unique('player_id');
        });
    }

    public function down(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            $table->dropUnique(['player_id']);
        });
    }
};
