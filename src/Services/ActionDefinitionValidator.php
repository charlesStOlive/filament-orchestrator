<?php

namespace CharlesStOlive\FilamentOrchestrator\Services;

use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorAction;
use CharlesStOlive\FilamentOrchestrator\Schemas\Definitions\ParameterDefinition;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ActionDefinitionValidator
{
    public function validate(OrchestratorAction $action): void
    {
        $action->loadMissing(['trigger.orchestration', 'targetNode']);
        $orchestration = $action->trigger->orchestration;
        $definition = $orchestration->schemaDefinition()->action($action->action);

        if ($definition === null) {
            throw ValidationException::withMessages(['action' => "L’action [{$action->action}] n’est pas déclarée par le schéma."]);
        }

        $this->validateTarget($action, $orchestration, $definition->targetRole);

        $parameters = $action->parameters ?? [];
        $definitions = collect($definition->parameters)->keyBy(
            fn (ParameterDefinition $parameter): string => $parameter->key,
        );
        $unknown = array_diff(array_keys($parameters), $definitions->keys()->all());

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'parameters' => 'Paramètres inconnus : '.implode(', ', $unknown).'.',
            ]);
        }

        foreach ($definitions as $parameter) {
            if (! array_key_exists($parameter->key, $parameters) && $parameter->default !== null) {
                $parameters[$parameter->key] = $parameter->default;
            }
        }

        $rules = $definitions->mapWithKeys(
            fn (ParameterDefinition $parameter): array => [$parameter->key => $this->rulesFor($parameter)],
        )->all();

        Validator::make($parameters, $rules)->validate();
        $action->parameters = $parameters;
    }

    private function validateTarget(OrchestratorAction $action, Orchestration $orchestration, ?string $expectedRole): void
    {
        $targetNode = $action->targetNode;
        $hasFallback = filled($action->target_role) || filled($action->target_key);

        if ($action->target_node_id !== null && $targetNode === null) {
            throw ValidationException::withMessages(['target_node_id' => 'La cible sélectionnée est introuvable.']);
        }

        if ($targetNode !== null) {
            if ($targetNode->orchestration_id !== $orchestration->getKey()) {
                throw ValidationException::withMessages(['target_node_id' => 'La cible doit appartenir à la même orchestration.']);
            }

            if ($hasFallback) {
                throw ValidationException::withMessages(['target_node_id' => 'Choisissez une cible précise ou une cible par rôle et clé, pas les deux.']);
            }

            if ($expectedRole !== null && $targetNode->role !== $expectedRole) {
                throw ValidationException::withMessages(['target_node_id' => "L’action [{$action->action}] attend une cible [{$expectedRole}]."]);
            }

            return;
        }

        if ($hasFallback && (! filled($action->target_role) || ! filled($action->target_key))) {
            throw ValidationException::withMessages(['target_key' => 'Une cible par clé requiert un rôle et une clé.']);
        }

        if ($expectedRole !== null && $action->target_role !== $expectedRole) {
            throw ValidationException::withMessages(['target_role' => "L’action [{$action->action}] attend une cible [{$expectedRole}]."]);
        }

        if ($expectedRole !== null && ! filled($action->target_key)) {
            throw ValidationException::withMessages(['target_key' => "L’action [{$action->action}] requiert une cible."]);
        }

        if ($hasFallback && ! $orchestration->nodes()
            ->where('role', $action->target_role)
            ->where('key', $action->target_key)
            ->exists()) {
            throw ValidationException::withMessages(['target_key' => 'La cible par rôle et clé est introuvable dans cette orchestration.']);
        }
    }

    private function rulesFor(ParameterDefinition $parameter): array
    {
        $rules = [$parameter->required ? 'required' : 'nullable'];
        $rules[] = match ($parameter->type) {
            'number' => 'numeric',
            'boolean' => 'boolean',
            'list', 'object' => 'array',
            default => 'string',
        };

        if ($parameter->type === 'list') {
            $rules[] = 'list';
        }

        if ($parameter->type === 'object') {
            $rules[] = function (string $attribute, mixed $value, callable $fail): void {
                if ($value !== [] && array_is_list($value)) {
                    $fail("Le champ {$attribute} doit être un objet clé/valeur.");
                }
            };
        }

        if ($parameter->type === 'select') {
            $rules[] = Rule::in(array_keys($parameter->options));
        }

        return $rules;
    }
}
