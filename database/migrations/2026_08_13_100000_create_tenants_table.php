<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Pemilik tenant. nullOnDelete, bukan cascade: menghapus akun
            // pengguna tidak boleh ikut menghapus workspace beserta seluruh
            // riwayat pesannya — kepemilikan cukup dipindahkan.
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('owner_email')->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active');

            // Dicerminkan dari app-pricing lewat entitlement, bukan sumber
            // kebenaran. Kosong = tenant internal Pesanvo tanpa langganan.
            $table->string('plan_slug')->nullable();
            $table->unsignedInteger('max_sessions')->default(1);
            $table->unsignedInteger('monthly_message_quota')->default(1000);
            $table->unsignedInteger('api_rate_limit_per_minute')->default(60);

            // Tenant internal (app-web/pricing/helpdesk/erp) dikecualikan
            // dari kuota dan tidak boleh dihapus lewat dashboard.
            $table->boolean('is_internal')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenant_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'admin', 'member'])->default('member');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_members');
        Schema::dropIfExists('tenants');
    }
};
