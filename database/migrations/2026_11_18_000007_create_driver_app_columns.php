<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('fcm_token', 255)->nullable()->after('photo_path');
            $table->dateTime('app_seen_at')->nullable()->after('fcm_token');
        });

        // Only the latest position is kept (privacy); updated while a trip is in progress.
        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('last_lat', 10, 7)->nullable()->after('end_km');
            $table->decimal('last_lng', 10, 7)->nullable()->after('last_lat');
            $table->dateTime('last_location_at')->nullable()->after('last_lng');
        });
    }

    public function down(): void
    {
        Schema::table('trips', fn (Blueprint $table) => $table->dropColumn(['last_lat', 'last_lng', 'last_location_at']));
        Schema::table('drivers', fn (Blueprint $table) => $table->dropColumn(['fcm_token', 'app_seen_at']));
    }
};
