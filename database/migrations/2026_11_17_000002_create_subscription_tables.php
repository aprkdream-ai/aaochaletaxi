<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->decimal('price_monthly', 12, 2);
            $table->decimal('price_yearly', 12, 2);
            $table->unsignedInteger('max_vehicles');
            $table->unsignedInteger('max_drivers');
            $table->unsignedInteger('max_branches')->default(1);
            $table->unsignedInteger('max_users')->default(5);
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('billing_cycle', 10); // trial | monthly | yearly
            $table->string('status', 20);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->index();
            $table->dateTime('grace_ends_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('auto_renew')->default(false);
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
