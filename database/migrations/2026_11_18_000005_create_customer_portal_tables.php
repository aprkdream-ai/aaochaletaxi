<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Unguessable token for the customer's tracking page (no login needed).
            $table->string('public_token', 40)->nullable()->unique()->after('booking_number');
            $table->boolean('phone_verified')->default(false)->after('customer_email');
        });

        // Backfill tracking tokens for bookings created before this migration.
        DB::table('bookings')->whereNull('public_token')->orderBy('id')->each(function ($b) {
            DB::table('bookings')->where('id', $b->id)->update(['public_token' => Str::random(40)]);
        });

        Schema::create('booking_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('comment', 1000)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_published', 'created_at']);
        });

        Schema::table('payment_orders', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('plan_id')->constrained('bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('booking_id'));
        Schema::dropIfExists('booking_reviews');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn(['public_token', 'phone_verified']);
        });
    }
};
