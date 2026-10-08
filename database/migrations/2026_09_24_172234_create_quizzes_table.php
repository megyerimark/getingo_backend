<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('question', 1000);
            $table->string('option_a', 1000);
            $table->string('option_b', 1000);
            $table->string('option_c', 1000);
            $table->string('option_d', 1000);
            $table->enum('correct_answer', ['a', 'b', 'c', 'd']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
