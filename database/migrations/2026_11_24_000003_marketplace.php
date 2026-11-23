<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('marketplace_enabled')->default(false)->after('business_type')->index();
        });

        Schema::create('tenant_service_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('city', 100);
            $table->string('city_key', 100);                  // Str::slug(city)
            $table->timestamps();

            $table->unique(['tenant_id', 'city_key']);
            $table->index('city_key');
        });

        // Customer request broadcast to up to N operators; first to accept gets the booking.
        Schema::create('marketplace_leads', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name', 150);
            $table->string('customer_phone', 15)->index();
            $table->string('customer_email', 150)->nullable();
            $table->string('type', 20);
            $table->foreignId('vehicle_category_id')->constrained('vehicle_categories')->restrictOnDelete();
            $table->string('from_city', 100);
            $table->string('city_key', 100)->index();
            $table->string('to_city', 100)->nullable();
            $table->string('pickup_address', 255);
            $table->string('drop_address', 255)->nullable();
            $table->dateTime('pickup_at');
            $table->dateTime('return_at')->nullable();
            $table->unsignedTinyInteger('rental_hours')->nullable();
            $table->unsignedTinyInteger('passengers')->default(1);
            $table->decimal('distance_km', 8, 1)->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('status', 12)->default('open');    // open | accepted | expired | cancelled
            $table->foreignId('accepted_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->dateTime('expires_at')->index();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
        Schema::create('marketplace_lead_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('marketplace_lead_id')->constrained('marketplace_leads')->cascadeOnDelete();
            $table->string('status', 10)->default('pending'); // pending | accepted | declined | lost | expired
            $table->decimal('estimate', 12, 2)->nullable();   // operator's fare estimate at broadcast time
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['marketplace_lead_id', 'tenant_id']);
            $table->index(['tenant_id', 'status']);
        });

        // Commission on marketplace bookings (snapshot %; amount fixed at completion; settled with platform settlements).
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('marketplace_lead_id')->nullable()->after('source')->constrained('marketplace_leads')->nullOnDelete();
            $table->decimal('commission_percent', 5, 2)->nullable()->after('marketplace_lead_id');
            $table->decimal('commission_amount', 12, 2)->nullable()->after('commission_percent');
            $table->foreignId('commission_settlement_id')->nullable()->after('commission_amount')->constrained('platform_settlements')->nullOnDelete();
        });
        Schema::table('platform_settlements', function (Blueprint $table) {
            $table->decimal('commission', 12, 2)->default(0)->after('fee');
            $table->unsignedInteger('commissions_count')->default(0)->after('payments_count');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settlements', fn (Blueprint $table) => $table->dropColumn(['commission', 'commissions_count']));
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commission_settlement_id');
            $table->dropConstrainedForeignId('marketplace_lead_id');
            $table->dropColumn(['commission_percent', 'commission_amount']);
        });
        Schema::dropIfExists('marketplace_lead_offers');
        Schema::dropIfExists('marketplace_leads');
        Schema::dropIfExists('tenant_service_cities');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['marketplace_enabled']);
            $table->dropColumn('marketplace_enabled');
        });
    }
};
