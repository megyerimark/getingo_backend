<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_submissions')) return;

        if (! Schema::hasColumn('project_submissions', 'started_at')) {
            Schema::table('project_submissions', function (Blueprint $table) {
                $table->timestamp('started_at')->nullable()->after('last_console_output');
            });
        }

        if (! Schema::hasColumn('project_submissions', 'expires_at')) {
            Schema::table('project_submissions', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('started_at')->index();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_submissions')) return;

        $columns = [];
        if (Schema::hasColumn('project_submissions', 'expires_at')) $columns[] = 'expires_at';
        if (Schema::hasColumn('project_submissions', 'started_at')) $columns[] = 'started_at';

        if ($columns !== []) {
            Schema::table('project_submissions', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
