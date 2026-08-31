<?php

namespace CharlesStOlive\FilamentOrchestrator\Filament\Concerns;

use CharlesStOlive\FilamentOrchestrator\Events\ContextualResourceCreated;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

trait HasContextualReturnAction
{
    public ?string $contextualCreationContext = null;

    public ?string $contextualReturnUrl = null;

    public ?string $contextualReturnLabel = null;

    protected function captureContextualCreation(): void
    {
        $context = request()->query('context');

        $this->contextualCreationContext = is_string($context) ? $context : null;
        $this->contextualReturnUrl = $this->safeReturnUrl(request()->query('return'));
        $label = request()->query('return_label');
        $this->contextualReturnLabel = is_string($label) ? $label : null;
    }

    protected function dispatchContextualResourceCreated(Model $record): void
    {
        if (filled($this->contextualCreationContext)) {
            Event::dispatch(new ContextualResourceCreated($record, $this->contextualCreationContext));
        }
    }

    protected function contextualRedirectUrl(string $default): string
    {
        return $this->contextualReturnUrl ?? $default;
    }

    protected function contextualReturnAction(): ?Action
    {
        $returnUrl = $this->contextualReturnUrl ?? $this->safeReturnUrl(request()->query('return'));

        if ($returnUrl === null) {
            return null;
        }

        $label = $this->contextualReturnLabel ?? request()->query('return_label');

        return Action::make('returnToOrchestration')
            ->label('Retour au parcours'.(is_string($label) && filled($label) ? ' '.$label : ''))
            ->icon('heroicon-o-arrow-left')
            ->color('gray')
            ->url($returnUrl);
    }

    private function safeReturnUrl(mixed $returnUrl): ?string
    {
        $panelUrl = url('/'.filament()->getCurrentPanel()->getPath());

        return is_string($returnUrl) && str_starts_with($returnUrl, $panelUrl.'/')
            ? $returnUrl
            : null;
    }
}
