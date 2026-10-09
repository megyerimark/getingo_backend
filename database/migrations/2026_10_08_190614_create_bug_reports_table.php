<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bug_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('title', 160);
            $table->text('description');

            $table->string('type', 30)->default('other');
            $table->string('priority', 20)->default('medium');

            $table->text('page_url')->nullable();
            $table->string('browser', 255)->nullable();
            $table->string('platform', 255)->nullable();

            $table->string('screenshot')->nullable();

            $table->string('status', 30)->default('new');

            $table->timestamps();

            $table->index('status');
            $table->index('priority');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_reports');
    }
};