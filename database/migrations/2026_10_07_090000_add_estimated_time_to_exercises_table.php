<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exercises') && ! Schema::hasColumn('exercises', 'estimated_time')) {
            Schema::table('exercises', function (Blueprint $table) {
                $table->unsignedInteger('estimated_time')->default(20)->after('difficulty');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('exercises') && Schema::hasColumn('exercises', 'estimated_time')) {
            Schema::table('exercises', fn (Blueprint $table) => $table->dropColumn('estimated_time'));
        }
    }
};
