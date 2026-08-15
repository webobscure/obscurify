<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_identities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained('customers')->cascadeOnDelete();

            // Only 'email_password' exists today. The column exists so a
            // future identity type (e.g. a social provider) can be added
            // as another row against the same Customer, never a schema
            // change — see docs/adr/022-customer-identity.md.
            $table->string('type');
            $table->string('identifier');
            $table->string('secret_hash');

            // Account lock protection (spec section 3) lives on the
            // credential being attacked, not on the Customer profile —
            // locking out one identity type must never affect another
            // future identity type on the same Customer.
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            $table->timestamps();

            $table->index('customer_id');
            // The actual login lookup key: one identity per (store, type,
            // normalized identifier) — this is what stops two accounts
            // registering the same email in the same store, unlike the
            // deliberately-non-unique customers.email column.
            $table->unique(['store_id', 'type', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_identities');
    }
};
