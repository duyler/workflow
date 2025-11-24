<?php

declare(strict_types=1);

namespace Duyler\Workflow\DSL;

final readonly class Retry
{
    public function __construct(
        public int $maxAttempts,
        public int $delaySeconds,
        public RetryBackoff $backoff = RetryBackoff::Fixed,
    ) {}

    public function calculateDelay(int $attempt): int
    {
        return match ($this->backoff) {
            RetryBackoff::Fixed => $this->delaySeconds,
            RetryBackoff::Linear => $this->delaySeconds * $attempt,
            RetryBackoff::Exponential => $this->delaySeconds * (2 ** ($attempt - 1)),
        };
    }
}
