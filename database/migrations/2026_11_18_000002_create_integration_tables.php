<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Online payment attempts (Razorpay orders). One row per checkout; fulfilled idempotently.
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('purpose', 30); // subscription (later: booking)
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->string('billing_cycle', 10)->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->char('currency', 3)->default('INR');
            $table->string('gateway', 20);
            $table->string('gateway_mode', 10); // test | live
            $table->string('gateway_order_id', 100)->unique();
            $table->string('gateway_payment_id', 100)->nullable()->unique();
            $table->string('status', 20)->default('created'); // created | paid | failed
            $table->foreignId('subscription_payment_id')->nullable()->constrained('subscription_payments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fulfilled_via', 20)->nullable(); // checkout | webhook
            $table->string('failure_reason', 255)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // Outbound message delivery log for every channel (mail, sms, whatsapp, push).
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('channel', 20)->index();
            $table->string('provider', 30)->nullable();
            $table->string('recipient', 255);
            $table->string('subject', 255)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->index(); // sent | failed | skipped
            $table->string('provider_message_id', 150)->nullable();
            $table->text('error')->nullable();
            $table->string('context', 100)->nullable(); // e.g. notification class / "admin.test"
            $table->timestamps();

            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('payment_orders');
    }
};
