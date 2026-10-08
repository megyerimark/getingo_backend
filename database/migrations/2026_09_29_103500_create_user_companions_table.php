<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_companions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 40)->default('Pixel');
            $table->unsignedInteger('care_points')->default(0);
            $table->unsignedTinyInteger('water')->default(70);
            $table->unsignedTinyInteger('hunger')->default(70);
            $table->unsignedTinyInteger('happiness')->default(70);
            $table->string('selected_skin', 50)->default('code-kitten');
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_companions');
    }
};
