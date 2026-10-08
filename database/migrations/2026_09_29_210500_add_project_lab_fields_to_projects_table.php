<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->longText('starter_html')->nullable()->after('solution');
            $table->longText('starter_css')->nullable()->after('starter_html');
            $table->longText('starter_javascript')->nullable()->after('starter_css');
            $table->string('validation_type', 40)->default('console_exact')->after('starter_javascript');
            $table->longText('expected_output')->nullable()->after('validation_type');
            $table->unsignedInteger('xp_reward')->default(25)->after('expected_output');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'starter_html',
                'starter_css',
                'starter_javascript',
                'validation_type',
                'expected_output',
                'xp_reward',
            ]);
        });
    }
};
