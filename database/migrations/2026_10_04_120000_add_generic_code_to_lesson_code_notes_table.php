<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lesson_code_notes')) {
            return;
        }

        if (!Schema::hasColumn('lesson_code_notes', 'code')) {
            Schema::table('lesson_code_notes', function (Blueprint $table) {
                $table->longText('code')->nullable()->after('javascript_code');
            });
        }

        if (!Schema::hasColumn('lesson_code_notes', 'language')) {
            Schema::table('lesson_code_notes', function (Blueprint $table) {
                $table->string('language', 20)->nullable()->after('code');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('lesson_code_notes')) {
            return;
        }

        $columns = [];

        if (Schema::hasColumn('lesson_code_notes', 'language')) {
            $columns[] = 'language';
        }

        if (Schema::hasColumn('lesson_code_notes', 'code')) {
            $columns[] = 'code';
        }

        if ($columns !== []) {
            Schema::table('lesson_code_notes', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};