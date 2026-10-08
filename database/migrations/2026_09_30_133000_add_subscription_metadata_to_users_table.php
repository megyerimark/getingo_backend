<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('subscription_billing_cycle', 20)->nullable()->after('subscription_status');
            $table->timestamp('premium_started_at')->nullable()->after('subscription_current_period_end');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['subscription_billing_cycle', 'premium_started_at']);
        });
    }
};
