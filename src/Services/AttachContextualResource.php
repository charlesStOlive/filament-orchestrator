<?php

namespace CharlesStOlive\FilamentOrchestrator\Services;

use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentOrchestrator\Models\OrchestratorNode;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class AttachContextualResource
{
    public function __construct(private readonly NodeManager $nodeManager) {}

    public function handle(Model $record, string $encryptedContext): ?OrchestratorNode
    {
        try {
            $context = json_decode(Crypt::decryptString($encryptedContext), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        $orchestration = Orchestration::find($context['orchestration_id'] ?? null);
        $role = $context['role'] ?? null;
        $definition = is_string($role) && $orchestration ? $orchestration->schemaDefinition()->node($role) : null;

        if ($definition === null || ! $record instanceof $definition->model) {
            return null;
        }

        $existingNode = $orchestration->nodes()
            ->where('role', $role)
            ->where('orchestratable_type', $record->getMorphClass())
            ->where('orchestratable_id', $record->getKey())
            ->first();

        if ($existingNode) {
            return $existingNode;
        }

        return $this->nodeManager->attach(
            orchestration: $orchestration,
            role: $role,
            key: $this->uniqueKey($orchestration, $role, $record),
            model: $record,
        );
    }

    private function uniqueKey(Orchestration $orchestration, string $role, Model $record): string
    {
        $label = collect(['slug', 'key', 'name', 'title'])
            ->map(fn (string $attribute): mixed => $record->getAttribute($attribute))
            ->first(fn (mixed $value): bool => filled($value));
        $base = Str::slug((string) ($label ?: $role.'-'.$record->getKey())) ?: $role.'-'.$record->getKey();
        $key = $base;
        $suffix = 2;

        while ($orchestration->nodes()->where('role', $role)->where('key', $key)->exists()) {
            $key = $base.'-'.$suffix++;
        }

        return $key;
    }
}
