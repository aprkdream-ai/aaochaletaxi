<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gap-free counters (invoice numbers, ticket numbers, later booking numbers).
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedSmallInteger('reminder_stage')->nullable()->after('auto_renew');
            $table->foreignId('superseded_by')->nullable()->after('reminder_stage')->constrained('subscriptions')->nullOnDelete();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained('subscriptions')->restrictOnDelete();
            $table->string('invoice_number', 40)->unique();
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('method', 20); // cash | upi | bank_transfer | cheque | razorpay | other
            $table->string('reference', 100)->nullable();
            $table->string('gateway', 20)->nullable();
            $table->string('gateway_order_id', 100)->nullable();
            $table->string('gateway_payment_id', 100)->nullable()->unique();
            $table->string('status', 20)->default('paid'); // paid | pending | failed
            $table->dateTime('paid_at')->nullable();
            $table->json('billing_snapshot')->nullable(); // seller + buyer details frozen at invoice time
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'paid_at']);
        });

        Schema::create('tenant_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('number', 60)->nullable();
            $table->string('file_path');
            $table->string('original_name', 200);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->string('review_notes', 500)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('number', 20)->unique();
            $table->string('subject', 150);
            $table->string('category', 20); // billing | technical | account | kyc | other
            $table->string('priority', 10)->default('normal'); // low | normal | high | urgent
            $table->string('status', 20)->default('open'); // open | awaiting_customer | resolved | closed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('last_reply_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'last_reply_at']);
        });

        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('from_staff')->default(false);
            $table->boolean('is_internal')->default(false);
            $table->text('body');
            $table->timestamps();

            $table->index(['support_ticket_id', 'id']);
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->foreignId('handled_by')->nullable()->after('handled_at')->constrained('users')->nullOnDelete();
            $table->string('admin_notes', 500)->nullable()->after('handled_by');
            $table->index(['type', 'handled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropIndex(['type', 'handled_at']);
            $table->dropConstrainedForeignId('handled_by');
            $table->dropColumn('admin_notes');
        });
        Schema::dropIfExists('support_ticket_replies');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('tenant_documents');
        Schema::dropIfExists('subscription_payments');
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('superseded_by');
            $table->dropColumn('reminder_stage');
        });
        Schema::dropIfExists('sequences');
    }
};
