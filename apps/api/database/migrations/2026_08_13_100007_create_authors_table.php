<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A blog post byline — deliberately its own entity rather than
     * reusing the staff User model: a store's public-facing "written by"
     * name/bio/avatar is editorial content (a guest writer, a pen name,
     * a brand voice) with no reason to be tied to a login-capable
     * account, and must survive that staff member's account being
     * removed.
     */
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('name');
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
