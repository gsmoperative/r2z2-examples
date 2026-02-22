<?php

namespace R2Z2Examples\Filter\Level2;

use R2Z2Examples\Filter\KillmailFilterInterface;

class MaxValueFilter implements KillmailFilterInterface
{
    public function __construct(
        private float $maxValue,
    ) {}

    public function filter(array $killmail): bool
    {
        return ($killmail['zkb']['totalValue'] ?? 0) <= $this->maxValue;
    }
}
