<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_companions', function (Blueprint $table) {
            $table->unsignedInteger('growth_points')->default(0);
            $table->timestamp('last_decay_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_companions', function (Blueprint $table) {
            $table->dropColumn(['growth_points', 'last_decay_at']);
        });
    }
};
