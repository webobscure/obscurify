<?php

namespace App\Console\Commands;

use App\Domain\Cms\Enums\BlogPostStatus;
use App\Domain\Cms\Models\BlogPost;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Flips a `scheduled` BlogPost to `published` once its `scheduled_at`
 * arrives. A poll rather than a delayed job because `scheduled_at` is
 * merchant-editable right up until it fires — a queued job scheduled at
 * creation time would need to be found and re-scheduled on every edit,
 * where a poll just re-reads the column. Scheduler wiring itself is an
 * ops concern outside this milestone, the same deliberate cut ADR-018
 * made for `outbox:process`/`webhooks:retry-failed`.
 */
class PublishScheduledBlogPostsCommand extends Command
{
    protected $signature = 'cms:publish-scheduled-posts';

    protected $description = 'Publish scheduled blog posts whose scheduled_at has arrived';

    public function handle(TenantContext $tenantContext): int
    {
        $posts = BlogPost::withoutGlobalScopes()
            ->where('status', BlogPostStatus::Scheduled->value)
            ->where('scheduled_at', '<=', now())
            ->get();

        $published = 0;

        foreach ($posts as $post) {
            $store = Store::query()->find($post->store_id);

            if ($store === null) {
                continue;
            }

            $tenantContext->scope($store, function () use ($post) {
                $post->update(['status' => BlogPostStatus::Published->value, 'published_at' => now()]);
            });

            $published++;
        }

        $this->info("Published {$published} scheduled blog post(s).");

        return self::SUCCESS;
    }
}
