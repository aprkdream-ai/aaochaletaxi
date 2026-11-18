<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 20);
            $table->string('city', 100);
            $table->string('address', 255)->nullable();
            $table->string('phone', 15)->nullable();
            $table->boolean('is_head_office')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        // tenant_id NULL = platform default category available to every operator.
        Schema::create('vehicle_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('slug', 60);
            $table->unsignedTinyInteger('seats')->default(4);
            $table->string('icon', 40)->default('car-front');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('vehicle_category_id')->constrained('vehicle_categories')->restrictOnDelete();
            $table->string('registration_number', 15);
            $table->string('make', 60);
            $table->string('model', 60);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color', 30)->nullable();
            $table->string('fuel_type', 15)->default('diesel');
            $table->unsignedTinyInteger('seats');
            $table->boolean('is_ac')->default(true);
            $table->string('ownership', 15)->default('owned'); // owned | attached | vendor
            $table->string('status', 15)->default('active');   // active | maintenance | inactive
            $table->boolean('is_available')->default(true);
            $table->date('insurance_expiry')->nullable();
            $table->date('permit_expiry')->nullable();
            $table->date('fitness_expiry')->nullable();
            $table->date('puc_expiry')->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->string('image_path')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'registration_number']);
            $table->index(['tenant_id', 'status', 'is_available']);
            $table->index(['tenant_id', 'insurance_expiry']);
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name', 150);
            $table->string('phone', 15);
            $table->string('alt_phone', 15)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('licence_number', 30);
            $table->string('licence_type', 30)->nullable(); // LMV, LMV-TR, HMV, Transport…
            $table->date('licence_expiry');
            $table->string('aadhaar_last4', 4)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('joined_on')->nullable();
            $table->string('address', 255)->nullable();
            $table->string('pay_type', 15)->default('monthly'); // monthly | per_trip | commission
            $table->decimal('pay_amount', 12, 2)->nullable();
            $table->string('status', 15)->default('active');   // active | on_leave | inactive
            $table->boolean('is_available')->default(true);
            $table->string('photo_path')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'phone']);
            $table->unique(['tenant_id', 'licence_number']);
            $table->index(['tenant_id', 'status', 'is_available']);
            $table->index(['tenant_id', 'licence_expiry']);
        });

        // Files for vehicles & drivers (RC, insurance, permit, licence, ID…) on the private disk.
        Schema::create('fleet_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->morphs('documentable');
            $table->string('type', 30);
            $table->string('number', 60)->nullable();
            $table->date('expires_on')->nullable();
            $table->string('file_path');
            $table->string('original_name', 200);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'expires_on']);
        });

        Schema::create('vehicle_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('service_date');
            $table->string('type', 20); // service | repair | tyres | battery | accident | other
            $table->string('description', 500);
            $table->unsignedInteger('odometer_km')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->string('vendor', 120)->nullable();
            $table->date('next_service_date')->nullable();
            $table->unsignedInteger('next_service_km')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'vehicle_id', 'service_date']);
        });

        Schema::create('vehicle_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('expense_date');
            $table->string('category', 20); // fuel | toll | parking | fine | cleaning | emi | insurance | permit | other
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 20)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'vehicle_id', 'expense_date']);
            $table->index(['tenant_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_expenses');
        Schema::dropIfExists('vehicle_maintenance');
        Schema::dropIfExists('fleet_documents');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('vehicle_categories');
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['branch_id']));
        Schema::dropIfExists('branches');
    }
};
