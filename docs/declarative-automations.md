# Declarative automations

Register a trusted PHP configuration array or a configuration reference:

```php
'automations' => ['guide' => 'config:automations.guide'],
```

A recipe declares `schema`, `version`, `rules`, `defaults`, `merge_objects`, `collections`, `resources`, `nodes`, `sequences`, `config`, and `triggers`. Existing class-based automations remain supported.

- `@definition.field` resolves a typed value; `{{item.title}}` interpolates a scalar.
- `each` expands a collection and provides `item`, `index`, `position`, and `next`.
- Collections declare `key_from`, optional `reserved_keys` and `defaults`. Persisted item identities survive renames and reordering.
- A node declares its schema `role` and `key`. A `reference` links an existing active model; otherwise `attributes` synchronizes an owned model. `identity_attribute` generates a unique model key. `required_relations` restricts linked resources.
- `resources` declares shared first-or-create models. `merge_attributes` preserves existing nested options.
- `media` declares collection, disk and paths. Only automation-tagged media are removed; existing unrelated media are preserved.
- Trigger definitions declare event, optional source and conditions, and actions with target, parameters, order, and optional `if` / `unless`.
- Removed managed nodes are removed with their owned records. Linked resources are retained. Previous definition mappings identify historical owned nodes without tags.
- Graph replacement retains the existing managed-automation semantics: saving a recipe regenerates its complete trigger graph.
- `legacy` optionally reconstructs missing definitions from orchestration values and role/key node mappings. Nodes can declare defaults and media fields. Recovery itself is read-only.

`RecipeForm::build()` renders trusted, cacheable component specifications: component class, name, methods (argument arrays), children, model options, state bindings, item labels and summaries. The configuration is application code, not an untrusted user-editable command format.

Use the generic `CreateAutomation` / `EditAutomation` Filament pages with a resource exposing `automationKey()` and `creationSteps()`. The resource retains application navigation and table choices.

## Optional MAP scene integration

Enable `integrations.map_scenes` only when filament-map is installed. The player then renders MapScene nodes with scenario points and routes feature events back to the scene node. Layer actions are validated against that scene, without application schema-name checks. Standalone map rendering remains supported. Contextual orchestrator resource attachment works independently of MAP.

Run `php artisan vendor:publish --tag=filament-orchestrator-assets --force` after updating the player.
