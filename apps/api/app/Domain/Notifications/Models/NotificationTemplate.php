<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationChannelType;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable message template (spec section 4). `subject`/`body_html`
 * are meaningful mainly for the Email channel; every channel always has
 * `body_text` as its baseline renderable content. Variables are
 * interpolated by NotificationTemplateRenderer from the same
 * Customer/Order/Payment/Shipment/Return/Store/Workflow context shape
 * WorkflowVariableResolver already builds for Automation.
 *
 * @property string $id
 * @property string $store_id
 * @property string|null $key
 * @property string $name
 * @property NotificationChannelType $channel
 * @property string $locale
 * @property string|null $subject
 * @property string $body_text
 * @property string|null $body_html
 * @property bool $is_active
 */
class NotificationTemplate extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'key',
        'name',
        'channel',
        'locale',
        'subject',
        'body_text',
        'body_html',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannelType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
