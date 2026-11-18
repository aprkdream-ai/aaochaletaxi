<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer tax invoices & credit notes (immutable once issued).
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('type', 15)->default('invoice');  // invoice | credit_note
            $table->string('number', 16);                    // GST: max 16 chars, unique per FY
            $table->foreignId('credit_note_for')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('status', 15)->default('issued'); // issued | credited
            $table->date('invoice_date');
            $table->json('seller');
            $table->json('buyer');
            $table->string('place_of_supply', 60)->nullable();
            $table->boolean('inter_state')->default(false);
            $table->decimal('taxable', 12, 2);
            $table->decimal('gst_percent', 5, 2);
            $table->decimal('cgst', 12, 2)->default(0);
            $table->decimal('sgst', 12, 2)->default(0);
            $table->decimal('igst', 12, 2)->default(0);
            $table->decimal('reimbursements', 12, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'invoice_date']);
            $table->index(['booking_id', 'type']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->string('sac', 10)->nullable();
            $table->boolean('taxable')->default(true);
            $table->decimal('amount', 12, 2);
        });

        // Business expenses not tied to a vehicle (rent, salaries, marketing…).
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('expense_date');
            $table->string('category', 30);
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 20)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'expense_date']);
        });

        // Driver payouts (append-only).
        Schema::create('driver_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedInteger('trips')->default(0);
            $table->decimal('earned', 12, 2)->default(0);     // computed at settlement time (snapshot)
            $table->decimal('deductions', 12, 2)->default(0); // advances / fines
            $table->decimal('amount', 12, 2);                 // paid
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->date('paid_on');
            $table->string('notes', 500)->nullable();
            $table->json('breakdown')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'driver_id', 'period_to']);
        });

        // Platform → operator payouts of advances collected online.
        Schema::create('platform_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->decimal('gross', 12, 2);
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('net', 12, 2);
            $table->unsignedInteger('payments_count');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->date('paid_on');
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('platform_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->unique()->constrained('payments')->restrictOnDelete();
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('advanced_reports')->default(false)->after('features');
        });
        DB::table('subscription_plans')->whereIn('code', ['growth', 'business'])->update(['advanced_reports' => true]);
    }

    public function down(): void
    {
        Schema::table('subscription_plans', fn (Blueprint $table) => $table->dropColumn('advanced_reports'));
        foreach (['platform_settlement_items', 'platform_settlements', 'driver_settlements', 'expenses', 'invoice_items', 'invoices'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
