<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stripe_invoice_id')->unique();
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->unsignedBigInteger('amount_due')->default(0);
            $table->string('currency', 8)->default('HUF');
            $table->string('billing_reason', 64)->nullable();
            $table->string('hosted_invoice_url', 2048)->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
