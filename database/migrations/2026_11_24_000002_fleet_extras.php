<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purchase & depreciation, attached-vehicle owner terms.
        Schema::table('vehicles', function (Blueprint $table) {
            $table->date('purchase_date')->nullable()->after('fastag_id');
            $table->decimal('purchase_cost', 12, 2)->nullable()->after('purchase_date');
            $table->string('depreciation_method', 5)->default('wdv')->after('purchase_cost');   // wdv | slm
            $table->decimal('depreciation_rate', 5, 2)->nullable()->after('depreciation_method'); // % per year
            $table->string('owner_name', 150)->nullable()->after('depreciation_rate');
            $table->string('owner_phone', 15)->nullable()->after('owner_name');
            $table->string('owner_pan', 10)->nullable()->after('owner_phone');
            $table->string('owner_bank', 300)->nullable()->after('owner_pan');
            $table->string('owner_rate_type', 15)->nullable()->after('owner_bank');            // per_km | per_trip | monthly | revenue_share
            $table->decimal('owner_rate', 12, 2)->nullable()->after('owner_rate_type');
        });

        // Tyres, batteries and other trackable parts.
        Schema::create('fleet_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 15);                       // tyre | battery | other
            $table->string('brand', 60);
            $table->string('model', 80)->nullable();          // size / spec
            $table->string('serial_number', 60)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->string('vendor', 120)->nullable();
            $table->date('warranty_until')->nullable();
            $table->string('status', 10)->default('in_stock'); // in_stock | fitted | scrapped
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('position', 20)->nullable();       // FL, FR, RL, RR, spare, main…
            $table->date('fitted_on')->nullable();
            $table->unsignedInteger('fitted_at_km')->nullable();
            $table->unsignedInteger('km_run')->default(0);     // km accumulated in previous fittings
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'type']);
            $table->index(['tenant_id', 'warranty_until']);
        });
        Schema::create('fleet_part_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('fleet_part_id')->constrained('fleet_parts')->cascadeOnDelete();
            $table->string('action', 10);                     // fit | remove | scrap
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('position', 20)->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->unsignedInteger('km_run')->nullable();    // on remove: km covered in this fitting
            $table->date('moved_on');
            $table->string('notes', 300)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Accidents, challans, theft, damage — with insurance claim tracking.
        Schema::create('vehicle_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('type', 15);                       // accident | challan | theft | damage | other
            $table->date('occurred_on');
            $table->string('place', 200)->nullable();
            $table->string('reference', 60)->nullable();      // challan no. / FIR no.
            $table->string('description', 1000);
            $table->decimal('amount', 12, 2)->default(0);     // fine / repair estimate
            $table->string('claim_status', 10)->default('none'); // none | filed | approved | rejected | settled
            $table->string('insurer', 120)->nullable();
            $table->string('claim_number', 60)->nullable();
            $table->decimal('settled_amount', 12, 2)->nullable();
            $table->string('status', 10)->default('open');    // open | closed
            $table->foreignId('expense_id')->nullable()->constrained('vehicle_expenses')->nullOnDelete();
            $table->string('photo_path')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'occurred_on']);
        });

        // Vehicle loans; each paid EMI is an `emi` vehicle expense linked by loan_id + installment_no.
        Schema::create('vehicle_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('lender', 120);
            $table->string('account_number', 40)->nullable();
            $table->decimal('principal', 12, 2);
            $table->decimal('interest_rate', 5, 2)->default(0);  // % per year
            $table->unsignedSmallInteger('tenure_months');
            $table->decimal('emi_amount', 12, 2);
            $table->date('first_emi_date');
            $table->string('status', 10)->default('active');     // active | closed
            $table->date('closed_on')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->after('vehicle_id')->constrained('vehicle_loans')->nullOnDelete();
            $table->unsignedSmallInteger('installment_no')->nullable()->after('loan_id');
            $table->unique(['loan_id', 'installment_no']);
        });

        // Attached / vendor vehicle owner payouts (append-only, like driver settlements).
        Schema::create('vehicle_owner_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('earned', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->date('paid_on');
            $table->string('notes', 500)->nullable();
            $table->json('breakdown')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'vehicle_id', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_owner_payouts');
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->dropUnique(['loan_id', 'installment_no']);
            $table->dropConstrainedForeignId('loan_id');
            $table->dropColumn('installment_no');
        });
        Schema::dropIfExists('vehicle_loans');
        Schema::dropIfExists('vehicle_incidents');
        Schema::dropIfExists('fleet_part_movements');
        Schema::dropIfExists('fleet_parts');
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn([
            'purchase_date', 'purchase_cost', 'depreciation_method', 'depreciation_rate',
            'owner_name', 'owner_phone', 'owner_pan', 'owner_bank', 'owner_rate_type', 'owner_rate',
        ]));
    }
};
