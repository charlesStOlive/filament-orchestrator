<?php

namespace CharlesStOlive\FilamentOrchestrator\Services;

use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class NodeManager
{
    public function attach(
        Orchestration $orchestration,
        string $role,
        string $key,
        Model $model,
        ?string $ownership = null,
        array $config = [],
        int $sortOrder = 0,
    ): OrchestratorNode {
        $definition = $orchestration->schemaDefinition()->node($role);

        if ($definition === null) {
            throw ValidationException::withMessages(['role' => "Le rôle [{$role}] n’est pas déclaré par le schéma."]);
        }

        $ownership ??= $definition->defaultOwnership;

        return $orchestration->nodes()->create([
            'role' => $role,
            'key' => $key,
            'orchestratable_type' => $model->getMorphClass(),
            'orchestratable_id' => $model->getKey(),
            'ownership' => $ownership,
            'config' => $config,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }
}
