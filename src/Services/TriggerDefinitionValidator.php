<?php

namespace CharlesStOlive\FilamentOrchestrator\Services;

use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorTrigger;
use Illuminate\Validation\ValidationException;

final class TriggerDefinitionValidator
{
    public function validate(OrchestratorTrigger $trigger): void
    {
        $trigger->loadMissing(['orchestration', 'sourceNode']);
        $orchestration = $trigger->orchestration;
        $schema = $orchestration?->schemaDefinition();
        $event = $schema?->event($trigger->event);

        if ($event === null) {
            throw ValidationException::withMessages(['event' => "L’événement [{$trigger->event}] n’est pas déclaré par le schéma."]);
        }

        if ($trigger->source_node_id !== null && $trigger->sourceNode === null) {
            throw ValidationException::withMessages(['source_node_id' => 'La source sélectionnée est introuvable.']);
        }

        if ($trigger->sourceNode !== null) {
            if ($trigger->sourceNode->orchestration_id !== $orchestration->getKey()) {
                throw ValidationException::withMessages(['source_node_id' => 'La source doit appartenir à la même orchestration.']);
            }

            if (filled($trigger->source_role) || filled($trigger->source_key)) {
                throw ValidationException::withMessages(['source_node_id' => 'Choisissez une source précise ou une source par rôle et clé, pas les deux.']);
            }

            if ($event->sourceRole !== null && $trigger->sourceNode->role !== $event->sourceRole) {
                throw ValidationException::withMessages(['source_node_id' => "L’événement [{$trigger->event}] attend une source [{$event->sourceRole}]."]);
            }

            return;
        }

        if ($event->sourceRole !== null) {
            if ($trigger->source_role !== null && $trigger->source_role !== $event->sourceRole) {
                throw ValidationException::withMessages(['source_role' => "L’événement [{$trigger->event}] attend une source [{$event->sourceRole}]."]);
            }

            $trigger->source_role = $event->sourceRole;
        }

        if ($trigger->source_role !== null && $schema->node($trigger->source_role) === null) {
            throw ValidationException::withMessages(['source_role' => "Le rôle source [{$trigger->source_role}] n’est pas déclaré par le schéma."]);
        }

        if (filled($trigger->source_key) && ! filled($trigger->source_role)) {
            throw ValidationException::withMessages(['source_key' => 'Une clé source requiert un rôle source.']);
        }

        if (filled($trigger->source_key) && ! $orchestration->nodes()
            ->where('role', $trigger->source_role)
            ->where('key', $trigger->source_key)
            ->exists()) {
            throw ValidationException::withMessages(['source_key' => 'La source par rôle et clé est introuvable dans cette orchestration.']);
        }
    }
}
