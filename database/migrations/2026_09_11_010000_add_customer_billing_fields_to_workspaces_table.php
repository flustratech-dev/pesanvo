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
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('billing_type', 20)->default('individu')->after('billing_email');
            $table->string('billing_company', 150)->nullable()->after('billing_type');
            $table->string('billing_bank_name', 80)->nullable()->after('billing_company');
            $table->string('billing_bank_account', 50)->nullable()->after('billing_bank_name');
            $table->string('billing_bank_holder', 120)->nullable()->after('billing_bank_account');
            $table->string('billing_province', 100)->nullable()->after('billing_bank_holder');
            $table->string('billing_city', 100)->nullable()->after('billing_province');
            $table->string('billing_district', 100)->nullable()->after('billing_city');
            $table->text('billing_address')->nullable()->after('billing_district');
            $table->string('billing_postal_code', 10)->nullable()->after('billing_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn([
                'billing_type',
                'billing_company',
                'billing_bank_name',
                'billing_bank_account',
                'billing_bank_holder',
                'billing_province',
                'billing_city',
                'billing_district',
                'billing_address',
                'billing_postal_code',
            ]);
        });
    }
};
