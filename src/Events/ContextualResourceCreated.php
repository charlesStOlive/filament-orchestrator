<?php

namespace CharlesStOlive\FilamentOrchestrator\Events;

use Illuminate\Database\Eloquent\Model;

final class ContextualResourceCreated
{
    public function __construct(
        public readonly Model $record,
        public readonly string $context,
    ) {}
}
