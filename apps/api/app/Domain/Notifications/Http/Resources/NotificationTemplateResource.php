<?php

namespace App\Domain\Notifications\Http\Resources;

use App\Domain\Notifications\Models\NotificationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationTemplate
 */
final class NotificationTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'channel' => $this->channel->value,
            'locale' => $this->locale,
            'subject' => $this->subject,
            'body_text' => $this->body_text,
            'body_html' => $this->body_html,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
