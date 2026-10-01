<?php

namespace CharlesStOlive\FilamentOrchestrator\Library;

use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentOrchestrator\Registry\AutomationRegistry;
use Illuminate\Support\Facades\Gate;

/**
 * Ce qu'on fait dans la bibliothèque d'une orchestration (charger, modifier, supprimer, taguer des images, et chaque
 * action de `filament-orchestrator.library.actions`) : réservé à qui en a le droit quand l'application le gère. Elle
 * définit alors une ability Gate `{Resource de l'automatisation}.library.{action}` — avec filament-permission-manager,
 * la permission `{liste}.library.{action}`, que `AutomationResource` déclare (famille « Bibliothèque », voir
 * `AutomationResource::permissionActions()`). Sans elle, l'action reste ouverte à qui ouvre la bibliothèque : aucune
 * dépendance à un gestionnaire de permissions.
 *
 * Ajouter des images à une journée, les ordonner ou les retirer, c'est modifier l'orchestration : son droit de
 * modification suffit.
 */
final class LibraryPermissions
{
    public const FAMILY = 'library';

    /** Les gestes de la bibliothèque elle-même, avec leur libellé dans l'écran des rôles. */
    public const ACTIONS = [
        'upload' => 'Charger des images',
        'edit' => 'Modifier une image',
        'tag' => 'Ajouter ou retirer des tags',
        'delete' => 'Supprimer des images',
    ];

    /**
     * Les actions à déclarer : `library.{action}` => libellé, gestes de la bibliothèque puis actions de l'application.
     *
     * @return array<string, string>
     */
    public static function actions(): array
    {
        $actions = collect(self::ACTIONS);

        foreach ((array) config('filament-orchestrator.library.actions', []) as $class) {
            $action = app($class);

            if ($action instanceof LibraryAction) {
                $actions->put($action->getName(), $action->getLabel());
            }
        }

        return $actions->mapWithKeys(fn (string $label, string $name): array => [self::FAMILY.'.'.$name => $label])->all();
    }

    public static function allows(Orchestration $orchestration, string $action): bool
    {
        $resource = app(AutomationRegistry::class)->for($orchestration);

        if ($resource === null) {
            return true;
        }

        $ability = $resource.'.'.self::FAMILY.'.'.$action;

        if (! Gate::has($ability)) {
            return true;
        }

        // La règle regarde l'enregistrement tel que sa Resource le connaît : sa sous-classe (ses états, son propriétaire).
        $model = $resource::getModel();

        if (! $orchestration instanceof $model && is_subclass_of($model, $orchestration::class)) {
            $orchestration = (new $model)->newFromBuilder($orchestration->getAttributes(), $orchestration->getConnectionName());
        }

        return Gate::allows($ability, [$orchestration]);
    }
}
