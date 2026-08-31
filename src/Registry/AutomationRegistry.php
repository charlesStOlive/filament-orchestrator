<?php

namespace CharlesStOlive\FilamentOrchestrator\Registry;

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
            ->map(function (string|OrchestrationAutomation $automation): OrchestrationAutomation {
                $instance = is_string($automation) ? $this->container->make($automation) : $automation;

                if (! $instance instanceof OrchestrationAutomation) {
                    throw new InvalidArgumentException('Every orchestrator automation must implement OrchestrationAutomation.');
                }

                return $instance;
            });
    }

    public function get(string $key): OrchestrationAutomation
    {
        return $this->all()->get($key)
            ?? throw new InvalidArgumentException("Unknown orchestrator automation [{$key}].");
    }
}
