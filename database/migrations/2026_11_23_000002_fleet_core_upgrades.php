<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('vehicle_type', 15)->default('car')->after('vehicle_category_id'); // FleetOptions::vehicleTypes()
            $table->date('road_tax_expiry')->nullable()->after('puc_expiry');                  // NULL = lifetime / not applicable
            $table->string('fastag_id', 30)->nullable()->after('road_tax_expiry');
        });

        // Fuel log lives on the fuel expense row (one money record, no duplicate amounts).
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->decimal('litres', 8, 2)->nullable()->after('amount');
            $table->unsignedInteger('odometer_km')->nullable()->after('litres');
            $table->boolean('full_tank')->default(true)->after('odometer_km');
            $table->index(['vehicle_id', 'category', 'odometer_km']);
        });

        Schema::create('vehicle_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->date('inspected_on');
            $table->unsignedInteger('odometer_km')->nullable();
            $table->json('items');                                  // {tyres: ok|issue, …}
            $table->unsignedTinyInteger('issues_count')->default(0);
            $table->string('notes', 500)->nullable();
            $table->string('photo_path')->nullable();               // private disk
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['vehicle_id', 'driver_id', 'inspected_on']);
            $table->index(['tenant_id', 'inspected_on', 'issues_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_inspections');
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->dropIndex(['vehicle_id', 'category', 'odometer_km']);
            $table->dropColumn(['litres', 'odometer_km', 'full_tank']);
        });
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn(['vehicle_type', 'road_tax_expiry', 'fastag_id']));
    }
};
