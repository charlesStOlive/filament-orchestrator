<?php

namespace CharlesStOlive\FilamentOrchestrator\Registry;

use CharlesStOlive\FilamentOrchestrator\Automations\Recipes\DeclarativeAutomation;
use CharlesStOlive\FilamentOrchestrator\Contracts\ManagedOrchestrationAutomation;
use CharlesStOlive\FilamentOrchestrator\Contracts\OrchestrationAutomation;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

final class AutomationRegistry
{
    private ?Collection $automations = null;

    public function __construct(private readonly Container $container) {}

    /** @return Collection<string, OrchestrationAutomation> */
    public function all(): Collection
    {
        return $this->automations ??= Collection::make(Config::get('filament-orchestrator.automations', []))
            ->map(function (string|array|OrchestrationAutomation $automation, string $configuredKey): OrchestrationAutomation {
                if (is_string($automation) && str_starts_with($automation, 'config:')) {
                    $path = substr($automation, 7);
                    $automation = Config::get($path);
                    if (! is_array($automation)) {
                        throw new InvalidArgumentException("Automation [{$configuredKey}] requires an array configuration at [{$path}].");
                    }
                }

                $instance = is_array($automation)
                    ? new DeclarativeAutomation($configuredKey, $automation)
                    : (is_string($automation) ? $this->container->make($automation) : $automation);

                if (! $instance instanceof OrchestrationAutomation) {
                    throw new InvalidArgumentException('Every orchestrator automation must implement OrchestrationAutomation.');
                }

                if ($instance instanceof ManagedOrchestrationAutomation && $instance->key() !== $configuredKey) {
                    throw new InvalidArgumentException("Automation [{$configuredKey}] declares the incompatible key [{$instance->key()}].");
                }

                return $instance;
            });
    }

    public function get(string $key): OrchestrationAutomation
    {
        return $this->all()->get($key)
            ?? throw new InvalidArgumentException("Unknown orchestrator automation [{$key}].");
    }

    public function getManaged(string $key): ManagedOrchestrationAutomation
    {
        $automation = $this->get($key);

        if (! $automation instanceof ManagedOrchestrationAutomation) {
            throw new InvalidArgumentException("Automation [{$key}] is not a managed orchestration automation.");
        }

        return $automation;
    }
}
