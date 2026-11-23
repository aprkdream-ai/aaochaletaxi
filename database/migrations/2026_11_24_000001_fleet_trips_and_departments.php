<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();          // cost centre code
            $table->string('head_name', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        // Fleet trips (Fleet module) have no booking: booking_id becomes nullable (unique index allows many NULLs).
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->change();
            $table->string('trip_number', 30)->nullable()->after('booking_id');
            $table->string('purpose', 200)->nullable()->after('trip_number');
            $table->foreignId('department_id')->nullable()->after('purpose')->constrained('departments')->nullOnDelete();
            $table->string('requested_by', 150)->nullable()->after('department_id');
            $table->string('requested_phone', 15)->nullable()->after('requested_by');
            $table->string('pickup_point', 255)->nullable()->after('requested_phone');
            $table->string('drop_point', 255)->nullable()->after('pickup_point');
            $table->unsignedTinyInteger('passengers')->nullable()->after('drop_point');
            $table->string('notes', 500)->nullable()->after('passengers');
            $table->string('cancel_reason', 300)->nullable()->after('completed_at');

            $table->unique(['tenant_id', 'trip_number']);
            $table->index(['tenant_id', 'department_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'trip_number']);
            $table->dropIndex(['tenant_id', 'department_id', 'completed_at']);
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['trip_number', 'purpose', 'requested_by', 'requested_phone', 'pickup_point', 'drop_point', 'passengers', 'notes', 'cancel_reason']);
        });
        Schema::dropIfExists('departments');
    }
};
