<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single post. Deliberately NOT versioned like Page/Theme — a blog
     * post's own draft/published/scheduled/archived `status` column is
     * the entire lifecycle (spec: no rollback requirement for posts), so
     * this is one mutable row, not a Post/PostVersion split. `body` is
     * plain rich-text content, not a section-instance array like
     * PageVersion.sections — a post is an article, not a themed layout.
     * `scheduled_at` is honored by the `cms:publish-scheduled-posts`
     * command (see that command's docblock for why it's a poll rather
     * than a delayed job, and docs/architecture/cms.md for the same
     * "scheduler wiring is an ops concern" cut ADR-018 already made for
     * outbox:process/webhooks:retry-failed).
     */
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('blog_id')->constrained('blogs')->cascadeOnDelete();
            $table->foreignUlid('author_id')->nullable()->constrained('authors')->nullOnDelete();

            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('featured_image_path')->nullable();

            $table->timestamps();

            $table->unique(['blog_id', 'slug']);
            $table->index(['blog_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
