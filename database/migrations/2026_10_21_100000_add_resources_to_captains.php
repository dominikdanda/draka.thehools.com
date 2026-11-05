<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('captains', function (Blueprint $table) {
            $table->unsignedBigInteger('money')->default(0);
            $table->unsignedBigInteger('materials')->default(0);
            $table->unsignedBigInteger('chemicals')->default(0);
            $table->unsignedBigInteger('respect_capacity')->default(0);
            $table->unsignedBigInteger('respect_demand')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('captains', function (Blueprint $table) {
            $table->dropColumn(['money', 'materials', 'chemicals', 'respect_capacity', 'respect_demand']);
        });
    }
};
