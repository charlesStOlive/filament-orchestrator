<?php

namespace CharlesStOlive\FilamentOrchestrator\Services;

use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;
use Illuminate\Validation\ValidationException;

final class NodeDefinitionValidator
{
    public function validate(OrchestratorNode $node): void
    {
        $orchestration = $node->orchestration()->first();
        $definition = $orchestration?->schemaDefinition()->node($node->role);

        if ($definition === null) {
            throw ValidationException::withMessages(['role' => "Le rôle [{$node->role}] n’est pas déclaré par le schéma."]);
        }

        if ($node->orchestratable_type !== (new $definition->model)->getMorphClass()) {
            throw ValidationException::withMessages(['orchestratable_type' => "Le rôle [{$node->role}] attend un modèle [{$definition->model}]."]);
        }

        if (! in_array($node->ownership, $definition->ownerships, true)) {
            throw ValidationException::withMessages(['ownership' => "Le mode [{$node->ownership}] n’est pas autorisé pour [{$node->role}]."]);
        }

        if (! $definition->multiple) {
            $duplicateRole = OrchestratorNode::query()
                ->where('orchestration_id', $node->orchestration_id)
                ->where('role', $node->role)
                ->when($node->exists, fn ($query) => $query->whereKeyNot($node->getKey()))
                ->exists();

            if ($duplicateRole) {
                throw ValidationException::withMessages(['role' => "Le rôle [{$node->role}] n’accepte qu’un seul élément."]);
            }
        }

        if ($node->orchestratable_id === null || ! $definition->model::query()->whereKey($node->orchestratable_id)->exists()) {
            throw ValidationException::withMessages(['orchestratable_id' => 'L’élément sélectionné est introuvable.']);
        }

        $sameModel = OrchestratorNode::query()
            ->where('orchestratable_type', $node->orchestratable_type)
            ->where('orchestratable_id', $node->orchestratable_id)
            ->when($node->exists, fn ($query) => $query->whereKeyNot($node->getKey()));

        if ($node->ownership === OrchestratorNode::OwnershipOwned) {
            if ((clone $sameModel)->exists()) {
                throw ValidationException::withMessages(['ownership' => 'Un élément propre ne peut être rattaché à aucun autre nœud.']);
            }

            return;
        }

        if ((clone $sameModel)->where('ownership', OrchestratorNode::OwnershipOwned)->exists()) {
            throw ValidationException::withMessages(['ownership' => 'Cet élément appartient exclusivement à une orchestration et ne peut pas être lié ailleurs.']);
        }
    }
}
