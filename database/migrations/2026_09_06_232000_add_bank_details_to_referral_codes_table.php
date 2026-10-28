<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_codes', function (Blueprint $table) {
            $table->string('bank_name', 50)->nullable()->after('is_active');
            $table->string('bank_account_number', 50)->nullable()->after('bank_name');
            $table->string('bank_account_name', 100)->nullable()->after('bank_account_number');
            $table->string('whatsapp_number', 30)->nullable()->after('bank_account_name');
            $table->text('notes')->nullable()->after('whatsapp_number');
            $table->string('approval_status', 20)->default('approved')->after('notes');
            $table->text('rejection_reason')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('rejection_reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_at');

            $table->unique('owner_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('referral_codes', function (Blueprint $table) {
            $table->dropUnique(['owner_user_id']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'bank_name',
                'bank_account_number',
                'bank_account_name',
                'whatsapp_number',
                'notes',
                'approval_status',
                'rejection_reason',
                'approved_at',
            ]);
        });
    }
};
