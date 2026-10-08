<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('longest_streak')->default(0)->after('current_streak');
            $table->date('last_learning_activity_on')->nullable()->after('longest_streak')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_learning_activity_on']);
            $table->dropColumn(['longest_streak', 'last_learning_activity_on']);
        });
    }
};
