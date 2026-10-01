<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Resources\Automations;

use CharlesStOlive\FilamentOrchestrator\Automations\NodeSynchronizer;
use CharlesStOlive\FilamentOrchestrator\Automations\Projection\CompiledProjection;
use CharlesStOlive\FilamentOrchestrator\Automations\Projection\Projection;
use CharlesStOlive\FilamentOrchestrator\Filament\Concerns\BelongsToConfiguredOrchestratorCluster;
use CharlesStOlive\FilamentOrchestrator\Library\LibraryPermissions;
use CharlesStOlive\FilamentOrchestrator\Models\Orchestration;
use CharlesStOlive\FilamentOrchestrator\Schemas\OrchestratorSchema;
use CharlesStOlive\FilamentOrchestrator\Services\AutomationGraphSynchronizer;
use Closure;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Base d'une automatisation : une ressource Filament classique — form(),
 * table(), getPages() — qui sait en plus projeter les données de son
 * formulaire dans une orchestration.
 *
 * La classe enfant déclare une méthode statique projection() recevant la
 * projection fournie par son schéma. Sa signature n'est volontairement pas
 * imposée ici : PHP interdisant de restreindre le type d'un paramètre hérité,
 * c'est le seul moyen pour l'automatisation de typer nativement la projection
 * de son schéma et de bénéficier de l'autocomplétion sur son vocabulaire.
 */
abstract class AutomationResource extends Resource
{
    use BelongsToConfiguredOrchestratorCluster;

    protected static ?string $model = Orchestration::class;

    protected static ?string $recordTitleAttribute = 'name';

    /** Clé stockée dans config->automation, qui rattache une orchestration à cette ressource. */
    protected static string $automationKey;

    /** @var class-string<OrchestratorSchema> */
    protected static string $automationSchema;

    protected static int $automationVersion = 1;

    /**
     * Les droits de la bibliothèque, au format de filament-permission-manager (qui les lit sans que ce package en
     * dépende) : la famille « Bibliothèque », une case par geste et par action de l'application. Une automatisation qui
     * ajoute ses propres familles redéclare `$permissionFamilies` avec `library`, et fusionne `parent::permissionActions()`.
     * Voir LibraryPermissions.
     *
     * @var array<string, string>
     */
    protected static array $permissionFamilies = [LibraryPermissions::FAMILY => 'Bibliothèque'];

    /** @return array<string, string> */
    public static function permissionActions(): array
    {
        return LibraryPermissions::actions();
    }

    public static function getAutomationKey(): string
    {
        return static::$automationKey;
    }

    public static function getAutomationVersion(): int
    {
        return static::$automationVersion;
    }

    public static function getAutomationSchema(): OrchestratorSchema
    {
        return app(static::$automationSchema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('config->automation', static::getAutomationKey());
    }

    /**
     * Règles appliquées aux données avant projection. Le formulaire Filament
     * valide déjà la saisie ; ces règles protègent les appels programmatiques.
     */
    public static function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }

    public static function createRecord(array $data): Orchestration
    {
        return DB::transaction(function () use ($data): Orchestration {
            static::validate($data);
            $compiled = static::compileProjection($data);

            // Le modèle de la Resource : une automatisation peut avoir le sien (une sous-classe d'Orchestration, avec
            // ses états ou son propriétaire), sur la même table.
            $orchestration = static::getModel()::query()->create(static::creationAttributes($compiled->definition));
            static::persist($orchestration, $compiled);

            return $orchestration->refresh();
        });
    }

    public static function updateRecord(Orchestration $record, array $data): Orchestration
    {
        return DB::transaction(function () use ($record, $data): Orchestration {
            static::ensureManaged($record);
            static::validate($data);
            $compiled = static::compileProjection($data);

            $record->update(static::updateAttributes($compiled->definition, $record));
            static::persist($record, $compiled);

            return $record->refresh();
        });
    }

    /** Définition normalisée, telle que réinjectée dans le formulaire d'édition. */
    public static function getDefinition(Orchestration $record): array
    {
        $definition = $record->config['automation_definition'] ?? null;

        return is_array($definition) ? $definition : [];
    }

    public static function compileProjection(array $data): CompiledProjection
    {
        if (! method_exists(static::class, 'projection')) {
            throw new LogicException(
                static::class.' doit déclarer une méthode statique projection() recevant '
                .static::getAutomationSchema()->projectionClass().'.',
            );
        }

        $schema = static::getAutomationSchema();
        /** @var class-string<Projection> $projectionClass */
        $projectionClass = $schema->projectionClass();
        $projection = $projectionClass::make($schema, $data);

        static::projection($projection);

        return $projection->compile();
    }

    protected static function persist(Orchestration $orchestration, CompiledProjection $compiled): void
    {
        $orchestration->update([
            'event_scope' => $orchestration->event_scope ?: $orchestration->key,
            'config' => [
                ...($orchestration->config ?? []),
                ...static::resolveConfig($compiled->config, $orchestration),
                'automation' => static::getAutomationKey(),
                'automation_version' => static::getAutomationVersion(),
                'automation_definition' => $compiled->definition,
            ],
        ]);

        app(NodeSynchronizer::class)->synchronize($orchestration, $compiled->nodes, static::getAutomationKey());
        app(AutomationGraphSynchronizer::class)->replace($orchestration, $compiled->graph);
    }

    protected static function validate(array $data): void
    {
        if ($rules = static::rules()) {
            Validator::make($data, $rules)->validate();
        }
    }

    protected static function creationAttributes(array $definition): array
    {
        $name = (string) ($definition['name'] ?? 'Orchestration');

        return [
            'schema' => static::getAutomationSchema()->key(),
            'name' => $name,
            'key' => static::uniqueOrchestrationKey(Str::slug($name) ?: static::getAutomationKey()),
            'description' => $definition['description'] ?? null,
            'initial_state' => static::initialState($definition),
            'is_active' => true,
        ];
    }

    protected static function updateAttributes(array $definition, Orchestration $record): array
    {
        return [
            'name' => $definition['name'] ?? $record->name,
            'description' => $definition['description'] ?? null,
        ];
    }

    protected static function initialState(array $definition): array
    {
        return [];
    }

    protected static function ensureManaged(Orchestration $record): void
    {
        if (($record->config['automation'] ?? null) !== static::getAutomationKey()) {
            throw ValidationException::withMessages([
                'orchestration' => 'Cette orchestration n’est pas gérée par cette automatisation.',
            ]);
        }
    }

    /** @param array<string, mixed|Closure> $config */
    protected static function resolveConfig(array $config, Orchestration $orchestration): array
    {
        return array_map(
            fn (mixed $value): mixed => $value instanceof Closure ? $value($orchestration) : $value,
            $config,
        );
    }

    protected static function uniqueOrchestrationKey(string $base): string
    {
        $candidate = $base;
        $suffix = 2;

        while (Orchestration::query()->where('key', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
