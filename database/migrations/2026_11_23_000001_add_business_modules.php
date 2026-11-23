<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing operators were all taxi businesses.
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('business_type', 10)->default('taxi')->after('status')->index();
        });

        // Price of the second module when a tenant runs Taxi + Fleet (excl. GST).
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('addon_price_monthly', 12, 2)->default(0)->after('price_yearly');
            $table->decimal('addon_price_yearly', 12, 2)->default(0)->after('addon_price_monthly');
        });

        // Snapshot of what was billed: `price` stays the total, `addon_price` is the part for the add-on module.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('business_type', 10)->nullable()->after('price');
            $table->decimal('addon_price', 12, 2)->default(0)->after('business_type');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn(['business_type', 'addon_price']));
        Schema::table('subscription_plans', fn (Blueprint $table) => $table->dropColumn(['addon_price_monthly', 'addon_price_yearly']));
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['business_type']);
            $table->dropColumn('business_type');
        });
    }
};
