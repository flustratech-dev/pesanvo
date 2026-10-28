<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->string('payout_number', 40)->nullable()->unique()->after('id');
            $table->unsignedInteger('fee_percent')->default(5)->after('amount');
            $table->unsignedBigInteger('fee_amount')->default(0)->after('fee_percent');
            $table->unsignedBigInteger('net_amount')->default(0)->after('fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropColumn(['payout_number', 'fee_percent', 'fee_amount', 'net_amount']);
        });
    }
};
