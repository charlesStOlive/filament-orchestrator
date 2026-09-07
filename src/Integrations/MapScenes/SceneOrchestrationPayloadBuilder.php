<?php

namespace CharlesStOlive\FilamentOrchestrator\Integrations\MapScenes;

use CharlesStOlive\FilamentMap\Models\MapScene;
use CharlesStOlive\FilamentOrchestrator\Services\OrchestrationPayloadBuilder;
use Illuminate\Database\Eloquent\Model;

class SceneOrchestrationPayloadBuilder extends OrchestrationPayloadBuilder
{
    protected function modelPayload(?Model $model): array
    {
        return [...parent::modelPayload($model), ...($model instanceof MapScene ? ['mapScene' => true] : [])];
    }

    protected function modelIsActive(?Model $model): bool
    {
        return parent::modelIsActive($model)
            && (! $model instanceof MapScene || (bool) $model->map?->is_active);
    }
}
