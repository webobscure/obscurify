<?php

namespace App\Domain\Automation\Support;

use App\Domain\Apps\Enums\ExtensionPoint;
use App\Domain\Apps\Models\AppExtension;
use App\Domain\Automation\Models\WorkflowVariable;
use Illuminate\Support\Collection;

/**
 * The variable-picker's read side (spec section 7) — merges the global
 * built-in catalog (WorkflowVariable, seeded by
 * RegisterBuiltInAutomationCatalog) with the current store's own
 * app-contributed variables (AppExtension at
 * ExtensionPoint::AutomationVariable — spec section 10: "No core
 * changes required").
 */
final class WorkflowVariableRegistry
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(): Collection
    {
        $builtIns = WorkflowVariable::query()->orderBy('source')->orderBy('key')->get()->map($this->fromBuiltIn(...));

        $appContributed = AppExtension::query()
            ->where('extension_point', ExtensionPoint::AutomationVariable->value)
            ->get()
            ->map($this->fromAppExtension(...));

        return $builtIns->concat($appContributed)->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function fromBuiltIn(WorkflowVariable $variable): array
    {
        return [
            'source' => $variable->source->value,
            'key' => $variable->key,
            'label' => $variable->label,
            'type' => $variable->type->value,
            'event_types' => $variable->event_types,
            'origin' => 'built_in',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromAppExtension(AppExtension $extension): array
    {
        return [
            'source' => $extension->config['source'] ?? null,
            'key' => $extension->config['key'] ?? null,
            'label' => $extension->config['label'] ?? null,
            'type' => $extension->config['type'] ?? null,
            'event_types' => $extension->config['event_types'] ?? null,
            'origin' => 'app',
            'installed_app_id' => $extension->installed_app_id,
        ];
    }
}
