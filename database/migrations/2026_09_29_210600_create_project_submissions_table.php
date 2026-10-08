<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->longText('html_code')->nullable();
            $table->longText('css_code')->nullable();
            $table->longText('javascript_code')->nullable();
            $table->longText('last_console_output')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'project_id']);
            $table->index(['user_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_submissions');
    }
};
