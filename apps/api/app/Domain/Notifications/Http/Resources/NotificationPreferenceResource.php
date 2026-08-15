<?php

namespace App\Domain\Notifications\Http\Resources;

use App\Domain\Notifications\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationPreference
 */
final class NotificationPreferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'email_enabled' => $this->email_enabled,
            'sms_enabled' => $this->sms_enabled,
            'push_enabled' => $this->push_enabled,
            'marketing_opt_in' => $this->marketing_opt_in,
            'transactional_only' => $this->transactional_only,
            'quiet_hours_start' => $this->quiet_hours_start,
            'quiet_hours_end' => $this->quiet_hours_end,
            'quiet_hours_timezone' => $this->quiet_hours_timezone,
        ];
    }
}
