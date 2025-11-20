<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('keycloak_client_id')->nullable()->after('product');
            $table->string('keycloak_client_secret')->nullable()->after('keycloak_client_id');
            $table->string('keycloak_client_uuid')->nullable()->after('keycloak_client_secret');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['keycloak_client_id', 'keycloak_client_secret', 'keycloak_client_uuid']);
        });
    }
};
