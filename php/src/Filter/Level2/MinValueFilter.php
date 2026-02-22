<?php

namespace R2Z2Examples\Filter\Level2;

use R2Z2Examples\Filter\KillmailFilterInterface;

class MinValueFilter implements KillmailFilterInterface
{
    public function __construct(
        private float $minValue,
    ) {}

    public function filter(array $killmail): bool
    {
        return ($killmail['zkb']['totalValue'] ?? 0) >= $this->minValue;
    }
}
