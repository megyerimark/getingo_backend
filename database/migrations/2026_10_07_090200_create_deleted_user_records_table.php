<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deleted_user_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_user_id')->nullable()->index();
            $table->string('name');
            $table->string('email')->index();
            $table->string('role', 32)->nullable();
            $table->text('reason');
            $table->string('evidence_path')->nullable();
            $table->string('evidence_original_name')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deleted_user_records');
    }
};
