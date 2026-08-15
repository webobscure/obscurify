<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Notifications\Models\NotificationTemplate;

final class CreateNotificationTemplate
{
    /**
     * @param  array{key?: ?string, name: string, channel: string, locale?: string, subject?: ?string, body_text: string, body_html?: ?string, is_active?: bool}  $data
     */
    public function handle(array $data): NotificationTemplate
    {
        return NotificationTemplate::query()->create([
            'key' => $data['key'] ?? null,
            'name' => $data['name'],
            'channel' => $data['channel'],
            'locale' => $data['locale'] ?? 'en',
            'subject' => $data['subject'] ?? null,
            'body_text' => $data['body_text'],
            'body_html' => $data['body_html'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
