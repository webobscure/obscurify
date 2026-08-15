<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // 'active'/'disabled' — a merchant-facing kill switch, distinct
            // from CustomerIdentity's failed_attempts/locked_until (which
            // is a temporary, self-clearing security lock, not a merchant
            // decision). Default 'active' so every pre-existing guest
            // Customer row remains usable once auth is added on top.
            $table->string('status')->default('active')->after('last_name');
            $table->timestamp('verified_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['status', 'verified_at']);
        });
    }
};
