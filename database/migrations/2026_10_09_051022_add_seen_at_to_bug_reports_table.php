<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bug_reports', function (Blueprint $table) {
            $table->timestamp('seen_at')
                ->nullable()
                ->after('status');

            $table->index('seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('bug_reports', function (Blueprint $table) {
            $table->dropIndex(['seen_at']);
            $table->dropColumn('seen_at');
        });
    }
};