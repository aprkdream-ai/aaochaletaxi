<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fare_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_category_id')->constrained('vehicle_categories')->cascadeOnDelete();
            $table->string('booking_type', 20);
            $table->decimal('base_fare', 10, 2)->default(0);
            $table->decimal('per_km', 8, 2)->default(0);
            $table->unsignedInteger('min_km')->default(0);          // minimum billable km (per day for round trips)
            $table->unsignedInteger('included_km')->default(0);     // rental packages
            $table->unsignedSmallInteger('included_hours')->default(0);
            $table->decimal('extra_km_rate', 8, 2)->default(0);
            $table->decimal('extra_hour_rate', 8, 2)->default(0);
            $table->decimal('driver_allowance', 8, 2)->default(0);  // per day
            $table->decimal('night_charge', 8, 2)->default(0);
            $table->time('night_start')->default('22:00:00');
            $table->time('night_end')->default('06:00:00');
            $table->unsignedSmallInteger('waiting_free_min')->default(15);
            $table->decimal('waiting_per_min', 6, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'vehicle_category_id', 'booking_type']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('booking_number', 30);
            $table->string('idempotency_key', 64)->nullable();
            $table->string('source', 20)->default('manual');   // manual | phone | whatsapp | website | marketplace
            $table->string('type', 20);
            $table->string('status', 20)->default('enquiry');  // enquiry | quoted | confirmed | assigned | started | completed | cancelled
            $table->string('payment_status', 20)->default('unpaid'); // unpaid | advance_paid | paid | refunded
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name', 150);
            $table->string('customer_phone', 15);
            $table->string('customer_email', 150)->nullable();
            $table->string('pickup_address', 255);
            $table->string('drop_address', 255)->nullable();
            $table->dateTime('pickup_at');
            $table->dateTime('return_at')->nullable();
            $table->foreignId('vehicle_category_id')->constrained('vehicle_categories')->restrictOnDelete();
            $table->unsignedTinyInteger('passengers')->default(1);
            $table->decimal('distance_km', 8, 1)->nullable();
            $table->unsignedSmallInteger('duration_min')->nullable();
            $table->unsignedSmallInteger('rental_hours')->nullable();
            $table->decimal('quoted_total', 12, 2)->nullable();
            $table->decimal('final_total', 12, 2)->nullable();
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->json('fare_snapshot')->nullable();
            $table->json('final_snapshot')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'booking_number']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'status', 'pickup_at']);
            $table->index(['tenant_id', 'customer_phone']);
        });

        Schema::create('booking_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('address', 255);
        });

        Schema::create('booking_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->json('breakdown');
            $table->decimal('total', 12, 2);
            $table->dateTime('valid_until')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['booking_id', 'version']);
        });

        Schema::create('booking_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Assignment window used for conflict checks (one active trip per booking).
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('status', 20)->default('assigned'); // assigned | started | completed | released
            $table->string('start_otp', 255)->nullable();       // hashed
            $table->unsignedInteger('start_km')->nullable();
            $table->unsignedInteger('end_km')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'status', 'start_at', 'end_at']);
            $table->index(['driver_id', 'status', 'start_at', 'end_at']);
        });

        // Append-only money ledger for bookings (advance, balance, refunds, adjustments).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);          // advance | balance | refund | adjustment
            $table->decimal('amount', 12, 2);    // negative for refunds/reversals
            $table->string('method', 20);        // cash | upi | card | bank_transfer | razorpay | other
            $table->string('reference', 100)->nullable();
            $table->foreignId('reversal_of')->nullable()->constrained('payments')->nullOnDelete();
            $table->dateTime('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        foreach (['payments', 'trips', 'booking_status_logs', 'booking_quotations', 'booking_stops', 'bookings', 'fare_rules'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
