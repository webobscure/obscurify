<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Notifications\Models\NotificationTemplate;

final class UpdateNotificationTemplate
{
    /**
     * @param  array{key?: ?string, name?: string, channel?: string, locale?: string, subject?: ?string, body_text?: string, body_html?: ?string, is_active?: bool}  $data
     */
    public function handle(NotificationTemplate $template, array $data): NotificationTemplate
    {
        $template->fill([
            'key' => array_key_exists('key', $data) ? $data['key'] : $template->key,
            'name' => $data['name'] ?? $template->name,
            'channel' => $data['channel'] ?? $template->channel->value,
            'locale' => $data['locale'] ?? $template->locale,
            'subject' => array_key_exists('subject', $data) ? $data['subject'] : $template->subject,
            'body_text' => $data['body_text'] ?? $template->body_text,
            'body_html' => array_key_exists('body_html', $data) ? $data['body_html'] : $template->body_html,
            'is_active' => $data['is_active'] ?? $template->is_active,
        ])->save();

        return $template->fresh();
    }
}
