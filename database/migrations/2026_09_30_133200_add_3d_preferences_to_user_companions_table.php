<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_companions', function (Blueprint $table) {
            $table->string('selected_room', 30)->default('studio')->after('selected_skin');
        });
    }

    public function down(): void
    {
        Schema::table('user_companions', function (Blueprint $table) {
            $table->dropColumn('selected_room');
        });
    }
};
