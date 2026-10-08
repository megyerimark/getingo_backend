<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('plan', 20)->default('free');
            $table->string('stripe_customer_id')->nullable()->unique();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('subscription_status', 40)->nullable();
            $table->timestamp('subscription_current_period_end')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['stripe_customer_id']);
            $table->dropUnique(['stripe_subscription_id']);
            $table->dropColumn([
                'plan',
                'stripe_customer_id',
                'stripe_subscription_id',
                'subscription_status',
                'subscription_current_period_end',
            ]);
        });
    }
};
