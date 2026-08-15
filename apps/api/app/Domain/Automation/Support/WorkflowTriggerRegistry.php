<?php

namespace App\Domain\Automation\Support;

use App\Domain\Apps\Enums\ExtensionPoint;
use App\Domain\Apps\Models\AppExtension;
use App\Domain\Webhooks\Support\PlatformEventCatalog;
use Illuminate\Support\Collection;

/**
 * The trigger-picker's read side (spec section 3: "Future events must
 * register automatically") — every event_type in PlatformEventCatalog
 * is triggerable with zero Automation-domain changes, plus whatever an
 * app has registered at ExtensionPoint::AutomationTrigger (spec section
 * 10) for this store.
 */
final class WorkflowTriggerRegistry
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(): Collection
    {
        $platformEvents = collect(PlatformEventCatalog::knownEventTypes())->map($this->fromPlatformEvent(...));

        $appContributed = AppExtension::query()
            ->where('extension_point', ExtensionPoint::AutomationTrigger->value)
            ->get()
            ->map($this->fromAppExtension(...));

        return $platformEvents->concat($appContributed)->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function fromPlatformEvent(string $eventType): array
    {
        return ['event_type' => $eventType, 'label' => $eventType, 'origin' => 'platform'];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromAppExtension(AppExtension $extension): array
    {
        return [
            'event_type' => $extension->config['event_type'] ?? null,
            'label' => $extension->config['label'] ?? ($extension->config['event_type'] ?? null),
            'origin' => 'app',
            'installed_app_id' => $extension->installed_app_id,
        ];
    }
}
