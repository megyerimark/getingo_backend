<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('student')->after('password')->index();
            $table->unsignedInteger('xp_points')->default(0)->after('role');
            $table->unsignedInteger('current_streak')->default(0)->after('xp_points');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'xp_points', 'current_streak']);
        });
    }
};
