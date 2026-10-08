<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->longText('example_html')->nullable()->after('example_code');
            $table->longText('example_css')->nullable()->after('example_html');
            $table->longText('example_javascript')->nullable()->after('example_css');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn([
                'example_html',
                'example_css',
                'example_javascript'
            ]);
        });
    }
};

